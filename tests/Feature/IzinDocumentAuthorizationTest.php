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
}
