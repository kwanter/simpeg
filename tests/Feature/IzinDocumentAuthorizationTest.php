<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;
use Tests\SimpegTestCase;

class IzinDocumentAuthorizationTest extends SimpegTestCase
{
    public function test_only_owner_can_download_izin_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/izin/secret.pdf', 'pdf-bytes');

        $owner = $this->createUserWithRole('user');
        $other = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'dokumen' => 'secret.pdf',
        ]);

        $this->get(route('izin.dokumen', $izin->uuid))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('izin.dokumen', $izin->uuid))->assertForbidden();
        $this->actingAs($owner)->get(route('izin.dokumen', $izin->uuid))->assertOk();
    }

    public function test_owner_can_download_document_from_legacy_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('dokumen/izin/legacy.pdf', 'legacy-pdf-bytes');
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'dokumen' => 'legacy.pdf',
        ]);

        $response = $this->actingAs($owner)->get(route('izin.dokumen', $izin->uuid));

        $response->assertOk();
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_assigned_approver_can_download_izin_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/izin/approver-copy.pdf', 'approver-pdf-bytes');
        $owner = $this->createUserWithRole('user');
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'dokumen' => 'approver-copy.pdf',
        ]);

        $this->actingAs($atasan)->get(route('izin.dokumen', $izin->uuid))->assertOk();
    }

    public function test_download_returns_not_found_when_izin_has_no_document_or_file_is_missing(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $withoutDocument = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'dokumen' => null,
        ]);
        $missingDocument = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'dokumen' => 'missing.pdf',
        ]);

        $this->actingAs($owner)->get(route('izin.dokumen', $withoutDocument->uuid))->assertNotFound();
        $this->actingAs($owner)->get(route('izin.dokumen', $missingDocument->uuid))->assertNotFound();
    }
}
