<?php

namespace Tests\Feature;

use App\Models\Cuti;
use App\Models\Pegawai;
use Tests\SimpegTestCase;

class CutiNoSuratUpdateTest extends SimpegTestCase
{
    public function test_admin_saves_the_letter_number_from_the_edit_nomor_form_payload(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo('update cuti');
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();

        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Tahunan',
            'status' => 'Disetujui Pimpinan',
            'no_surat_cuti' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('cuti.update', $cuti->uuid), ['no_surat_cuti' => 'W8A1IPXI2026'])
            ->assertRedirect(route('cuti.show', $cuti->uuid))
            ->assertSessionHasNoErrors();

        $this->assertSame('W8A1IPXI2026', $cuti->fresh()->no_surat_cuti);
    }

    public function test_letter_number_is_still_required_when_the_payload_is_a_no_surat_update(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo('update cuti');
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();

        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Tahunan',
            'status' => 'Disetujui Pimpinan',
            'no_surat_cuti' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('cuti.update', $cuti->uuid), ['no_surat_cuti' => ''])
            ->assertSessionHasErrors('no_surat_cuti');

        $this->assertNull($cuti->fresh()->no_surat_cuti);
    }
}
