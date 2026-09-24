<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Tests\SimpegTestCase;

class IzinNoSuratBoundaryTest extends SimpegTestCase
{
    private function createAtasanApprovedIzin(): Izin
    {
        return Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Disetujui Atasan',
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'no_surat_izin' => null,
            'alasan' => 'Existing reason',
        ]);
    }

    public function test_real_edit_form_payload_is_treated_as_a_no_surat_update(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = $this->createAtasanApprovedIzin();

        $response = $this->actingAs($admin)->post(route('izin.update', $izin->uuid), [
            '_token' => 'test-token',
            '_method' => 'PUT',
            'no_surat_izin' => 'IZIN-2026-001',
        ]);

        $response->assertRedirect(route('izin.index'));
        $response->assertSessionHas('success', 'Nomor surat izin berhasil diperbarui');

        $persisted = $izin->fresh();
        $this->assertSame('IZIN-2026-001', $persisted->no_surat_izin);
        $this->assertSame('Disetujui Atasan', $persisted->status);
        $this->assertSame('Existing reason', $persisted->alasan);
    }

    public function test_payload_with_a_fourth_field_is_not_treated_as_a_no_surat_update(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = $this->createAtasanApprovedIzin();

        $response = $this->actingAs($admin)->post(route('izin.update', $izin->uuid), [
            '_token' => 'test-token',
            '_method' => 'PUT',
            'no_surat_izin' => 'IZIN-2026-001',
            'catatan_pimpinan' => 'extra field',
        ]);

        $response->assertSessionMissing('success');
        $response->assertSessionHasErrors(['jenis_izin', 'tanggal_mulai']);
        $this->assertNull($izin->fresh()->no_surat_izin);
    }

    public function test_unapproved_izin_is_not_treated_as_a_no_surat_update(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'no_surat_izin' => null,
        ]);

        $response = $this->actingAs($owner)->put(route('izin.update', $izin->uuid), [
            'no_surat_izin' => 'IZIN-2026-002',
        ]);

        $response->assertSessionMissing('success');
        $response->assertSessionHasErrors(['jenis_izin', 'tanggal_mulai']);
        $this->assertNull($izin->fresh()->no_surat_izin);
    }
}
