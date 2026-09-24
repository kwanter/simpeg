<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SimpegTestCase;

class IzinDocumentLifecycleTest extends SimpegTestCase
{
    private function createOwner(): array
    {
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();

        return [$user, $pegawai];
    }

    private function updatePayload(Pegawai $atasan, Pegawai $pimpinan): array
    {
        return [
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Keperluan pribadi',
        ];
    }

    private function createUnverifiedIzin(Pegawai $pegawai, Pegawai $atasan, Pegawai $pimpinan, ?string $dokumen): Izin
    {
        return Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'dokumen' => $dokumen,
        ]);
    }

    public function test_full_update_replaces_document_and_removes_the_previous_file(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        Storage::fake('local');

        [$owner, $pegawai] = $this->createOwner();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = $this->createUnverifiedIzin($pegawai, $atasan, $pimpinan, 'previous.pdf');
        Storage::disk('local')->put('dokumen/izin/previous.pdf', 'previous-bytes');

        $response = $this->actingAs($owner)->put(route('izin.update', $izin->uuid), array_merge(
            $this->updatePayload($atasan, $pimpinan),
            ['dokumen' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf')]
        ));

        $response->assertRedirect(route('izin.index'));
        $stored = $izin->fresh()->dokumen;
        $this->assertNotSame('previous.pdf', $stored);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', (string) $stored);
        Storage::disk('local')->assertExists('dokumen/izin/'.$stored);
        Storage::disk('local')->assertMissing('dokumen/izin/previous.pdf');

        Carbon::setTestNow();
    }

    public function test_full_update_removes_the_replaced_document_from_the_legacy_public_disk(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $pegawai] = $this->createOwner();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = $this->createUnverifiedIzin($pegawai, $atasan, $pimpinan, 'legacy-previous.pdf');
        Storage::disk('public')->put('dokumen/izin/legacy-previous.pdf', 'legacy-bytes');

        $response = $this->actingAs($owner)->put(route('izin.update', $izin->uuid), array_merge(
            $this->updatePayload($atasan, $pimpinan),
            ['dokumen' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf')]
        ));

        $response->assertRedirect(route('izin.index'));
        Storage::disk('public')->assertMissing('dokumen/izin/legacy-previous.pdf');

        Carbon::setTestNow();
    }

    public function test_full_update_without_a_document_keeps_the_existing_document(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        Storage::fake('local');

        [$owner, $pegawai] = $this->createOwner();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = $this->createUnverifiedIzin($pegawai, $atasan, $pimpinan, 'kept.pdf');
        Storage::disk('local')->put('dokumen/izin/kept.pdf', 'kept-bytes');

        $response = $this->actingAs($owner)->put(
            route('izin.update', $izin->uuid),
            $this->updatePayload($atasan, $pimpinan)
        );

        $response->assertRedirect(route('izin.index'));
        $this->assertSame('kept.pdf', $izin->fresh()->dokumen);
        Storage::disk('local')->assertExists('dokumen/izin/kept.pdf');

        Carbon::setTestNow();
    }

    public function test_full_update_adds_a_document_when_none_existed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $pegawai] = $this->createOwner();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = $this->createUnverifiedIzin($pegawai, $atasan, $pimpinan, null);

        $response = $this->actingAs($owner)->put(route('izin.update', $izin->uuid), array_merge(
            $this->updatePayload($atasan, $pimpinan),
            ['dokumen' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf')]
        ));

        $response->assertRedirect(route('izin.index'));
        $stored = $izin->fresh()->dokumen;
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', (string) $stored);
        Storage::disk('local')->assertExists('dokumen/izin/'.$stored);

        Carbon::setTestNow();
    }

    public function test_unsupported_document_on_update_is_rejected_and_keeps_the_existing_document(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        Storage::fake('local');

        [$owner, $pegawai] = $this->createOwner();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = $this->createUnverifiedIzin($pegawai, $atasan, $pimpinan, 'existing.pdf');
        Storage::disk('local')->put('dokumen/izin/existing.pdf', 'existing-bytes');

        $response = $this->actingAs($owner)->put(route('izin.update', $izin->uuid), array_merge(
            $this->updatePayload($atasan, $pimpinan),
            ['dokumen' => UploadedFile::fake()->create('lampiran.txt', 10, 'text/plain')]
        ));

        $response->assertSessionHasErrors('dokumen');
        $this->assertSame(
            'The dokumen field must be a file of type: pdf, jpg, jpeg, png.',
            session('errors')->first('dokumen')
        );
        $this->assertSame('existing.pdf', $izin->fresh()->dokumen);
        Storage::disk('local')->assertExists('dokumen/izin/existing.pdf');

        Carbon::setTestNow();
    }
}
