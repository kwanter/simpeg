<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class IzinValidationRulesTest extends SimpegTestCase
{
    private function createPegawaiUser(): array
    {
        $user = $this->createUserWithRole('pegawai', ['status' => '1']);
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $user->givePermissionTo('create izin');
        $user->givePermissionTo('update izin');

        return [$user, $pegawai];
    }

    private function createApprover(string $role): Pegawai
    {
        $user = $this->createUserWithRole($role);

        return Pegawai::where('nip', $user->nip)->firstOrFail();
    }

    private function validPayload(Pegawai $atasan, Pegawai $pimpinan): array
    {
        return [
            'jenis_izin' => 'Izin Sakit',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Sakit demam',
        ];
    }

    public function test_unknown_jenis_izin_is_rejected(): void
    {
        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), array_merge(
            $this->validPayload($atasan, $pimpinan),
            ['jenis_izin' => 'Izin Tidak Dikenal']
        ));

        $response->assertSessionHasErrors('jenis_izin');
        $this->assertSame(0, Izin::count());
    }

    public function test_tanggal_selesai_before_tanggal_mulai_is_rejected(): void
    {
        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), array_merge(
            $this->validPayload($atasan, $pimpinan),
            ['tanggal_mulai' => '2026-01-06', 'tanggal_selesai' => '2026-01-05']
        ));

        $response->assertSessionHasErrors('tanggal_selesai');
        $this->assertSame(0, Izin::count());
    }

    public function test_missing_approver_uuids_are_required(): void
    {
        [$user] = $this->createPegawaiUser();

        $payload = [
            'jenis_izin' => 'Izin Sakit',
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Sakit demam',
        ];

        $response = $this->actingAs($user)->post(route('izin.store'), $payload);

        $response->assertSessionHasErrors(['atasan_pimpinan_uuid', 'pimpinan_uuid']);
        $this->assertSame(0, Izin::count());
    }

    public function test_unknown_approver_uuid_is_rejected(): void
    {
        [$user] = $this->createPegawaiUser();
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), array_merge(
            $this->validPayload($pimpinan, $pimpinan),
            ['atasan_pimpinan_uuid' => (string) Str::uuid()]
        ));

        $response->assertSessionHasErrors('atasan_pimpinan_uuid');
        $this->assertSame(0, Izin::count());
    }

    public function test_single_level_izin_requires_time_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Keluar Kantor',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-05',
            'alasan' => 'Ke luar sebentar',
        ]);

        $response->assertSessionHasErrors(['jam_mulai', 'jam_selesai']);
        $this->assertSame(0, Izin::count());

        Carbon::setTestNow();
    }

    public function test_single_level_izin_uses_pinned_indonesian_same_day_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Keluar Kantor',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-06',
            'tanggal_selesai' => '2026-01-06',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'alasan' => 'Besok',
        ]);

        $response->assertSessionHasErrors(['tanggal_mulai', 'tanggal_selesai']);
        $this->assertSame(
            'Izin keluar kantor hanya dapat diajukan pada hari ini.',
            session('errors')->first('tanggal_mulai')
        );

        Carbon::setTestNow();
    }

    public function test_single_level_izin_alasan_over_500_characters_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Keluar Kantor',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-05',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'alasan' => str_repeat('a', 501),
        ]);

        $response->assertSessionHasErrors('alasan');
        $this->assertSame(0, Izin::count());

        Carbon::setTestNow();
    }

    public function test_tidak_masuk_izin_tanggal_mulai_in_the_past_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-02',
            'tanggal_selesai' => '2026-01-05',
            'alasan' => 'Mundur',
        ]);

        $response->assertSessionHasErrors('tanggal_mulai');
        $this->assertSame(0, Izin::count());

        Carbon::setTestNow();
    }

    public function test_tidak_masuk_izin_alasan_over_500_characters_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => str_repeat('a', 501),
        ]);

        $response->assertSessionHasErrors('alasan');
        $this->assertSame(0, Izin::count());

        Carbon::setTestNow();
    }

    public function test_two_level_izin_has_no_alasan_length_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');
        $longAlasan = str_repeat('a', 900);

        $response = $this->actingAs($user)->post(route('izin.store'), array_merge(
            $this->validPayload($atasan, $pimpinan),
            ['alasan' => $longAlasan]
        ));

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            $longAlasan,
            Izin::where('pegawai_uuid', $pegawai->uuid)->firstOrFail()->alasan
        );

        Carbon::setTestNow();
    }

    public function test_unsupported_dokumen_type_is_rejected(): void
    {
        Storage::fake('local');

        [$user] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), array_merge(
            $this->validPayload($atasan, $pimpinan),
            ['dokumen' => UploadedFile::fake()->create('lampiran.txt', 10, 'text/plain')]
        ));

        $response->assertSessionHasErrors('dokumen');
        $this->assertSame(0, Izin::count());
    }

    public function test_supported_dokumen_is_stored_on_the_private_disk(): void
    {
        Storage::fake('local');

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), array_merge(
            $this->validPayload($atasan, $pimpinan),
            ['dokumen' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')]
        ));

        $response->assertSessionHasNoErrors();
        $izin = Izin::where('pegawai_uuid', $pegawai->uuid)->firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', (string) $izin->dokumen);
        Storage::disk('local')->assertExists('dokumen/izin/'.$izin->dokumen);
    }

    public function test_update_rejects_invalid_jenis_izin(): void
    {
        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
        ]);

        $response = $this->actingAs($user)->put(route('izin.update', $izin->uuid), array_merge(
            $this->validPayload($atasan, $pimpinan),
            ['jenis_izin' => 'Izin Tidak Dikenal']
        ));

        $response->assertSessionHasErrors('jenis_izin');
        $this->assertNotSame('Izin Tidak Dikenal', $izin->fresh()->jenis_izin);
    }

    public function test_no_surat_update_rejects_duplicate_number(): void
    {
        $admin = $this->createUserWithRole('admin');
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');
        [, $pegawai] = $this->createPegawaiUser();

        Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'verifikasi_atasan' => 'Disetujui',
            'no_surat_izin' => 'SURAT-001',
        ]);

        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'verifikasi_atasan' => 'Disetujui',
            'no_surat_izin' => null,
        ]);

        $response = $this->actingAs($admin)->put(route('izin.update', $izin->uuid), [
            'no_surat_izin' => 'SURAT-001',
        ]);

        $response->assertSessionHasErrors('no_surat_izin');
        $this->assertNull($izin->fresh()->no_surat_izin);
    }
}
