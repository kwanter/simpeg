<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Tests\SimpegTestCase;

class IzinApprovalEndpointsTest extends SimpegTestCase
{
    public function test_assigned_atasan_can_approve_through_the_http_endpoint(): void
    {
        $owner = $this->createUserWithRole('user');
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'jenis_izin' => 'Izin Keperluan Pribadi', // two-level: atasan approval is not final
            'status' => 'Diajukan',
        ]);

        $response = $this->actingAs($atasan)->post(route('izin.proses-verifikasi-atasan', $izin->uuid), [
            'verifikasi_atasan' => 'Disetujui',
            'catatan_atasan' => 'Verified at supervisor level',
        ]);

        $response->assertRedirect(route('izin.index'));
        $response->assertSessionHas('success', 'Verifikasi atasan berhasil dilakukan');
        $this->assertDatabaseHas('izin', [
            'uuid' => $izin->uuid,
            'status' => 'Disetujui Atasan',
            'verifikasi_atasan' => 'Disetujui',
            'catatan_atasan' => 'Verified at supervisor level',
        ]);
    }

    public function test_assigned_pimpinan_can_approve_after_atasan_through_the_http_endpoint(): void
    {
        $owner = $this->createUserWithRole('user');
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $pimpinan = $this->createUserWithRole('pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $pimpinanPegawai = Pegawai::where('nip', $pimpinan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'pimpinan_uuid' => $pimpinanPegawai->uuid,
            'jenis_izin' => 'Izin Keperluan Pribadi', // two-level: a pimpinan step exists
            'status' => 'Disetujui Atasan',
            'verifikasi_atasan' => 'Disetujui',
        ]);

        $response = $this->actingAs($pimpinan)->post(route('izin.proses-verifikasi-pimpinan', $izin->uuid), [
            'verifikasi_pimpinan' => 'Disetujui',
            'catatan_pimpinan' => 'Verified at executive level',
        ]);

        $response->assertRedirect(route('izin.index'));
        $response->assertSessionHas('success', 'Verifikasi pimpinan berhasil dilakukan');
        $this->assertDatabaseHas('izin', [
            'uuid' => $izin->uuid,
            'status' => 'Disetujui',
            'verifikasi_pimpinan' => 'Disetujui',
            'catatan_pimpinan' => 'Verified at executive level',
        ]);
    }

    public function test_invalid_atasan_decision_is_rejected_without_changing_izin(): void
    {
        $owner = $this->createUserWithRole('user');
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'status' => 'Diajukan',
        ]);

        $response = $this->actingAs($atasan)->post(route('izin.proses-verifikasi-atasan', $izin->uuid), [
            'verifikasi_atasan' => 'Ditunda',
        ]);

        $response->assertSessionHasErrors('verifikasi_atasan');
        $this->assertDatabaseHas('izin', [
            'uuid' => $izin->uuid,
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
        ]);
    }

    public function test_unassigned_atasan_is_forbidden_from_approving_izin(): void
    {
        $owner = $this->createUserWithRole('user');
        $assignedAtasan = $this->createUserWithRole('atasan-pimpinan');
        $otherAtasan = $this->createUserWithRole('atasan-pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $assignedPegawai = Pegawai::where('nip', $assignedAtasan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $assignedPegawai->uuid,
            'status' => 'Diajukan',
        ]);

        $this->actingAs($otherAtasan)
            ->post(route('izin.proses-verifikasi-atasan', $izin->uuid), [
                'verifikasi_atasan' => 'Disetujui',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('izin', [
            'uuid' => $izin->uuid,
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
        ]);
    }

    public function test_assigned_atasan_gets_verification_page_with_izin_details_and_post_target(): void
    {
        $owner = $this->createUserWithRole('user', ['nip' => 'EMP-OWNER-001'], ['nama' => 'Ayu Test']);
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'alasan' => 'Family appointment',
            'status' => 'Diajukan',
        ]);

        $response = $this->actingAs($atasan)->get(route('izin.verifikasi-atasan', $izin->uuid));

        $response->assertOk()
            ->assertViewIs('izin.verifikasi-atasan')
            ->assertSee('Ayu Test')
            ->assertSee('Family appointment')
            ->assertSee(route('izin.proses-verifikasi-atasan', $izin->uuid), false)
            ->assertSee('name="verifikasi_atasan"', false);
    }

    public function test_assigned_pimpinan_gets_verification_page_with_atasan_decision_and_note(): void
    {
        $owner = $this->createUserWithRole('user', ['nip' => 'EMP-OWNER-002'], ['nama' => 'Budi Test']);
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $pimpinan = $this->createUserWithRole('pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $pimpinanPegawai = Pegawai::where('nip', $pimpinan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'pimpinan_uuid' => $pimpinanPegawai->uuid,
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Disetujui Atasan',
            'verifikasi_atasan' => 'Disetujui',
            'catatan_atasan' => 'Atasan sudah memeriksa',
        ]);

        $response = $this->actingAs($pimpinan)->get(route('izin.verifikasi-pimpinan', $izin->uuid));

        $response->assertOk()
            ->assertViewIs('izin.verifikasi-pimpinan')
            ->assertSee('Budi Test')
            ->assertSee('Atasan sudah memeriksa')
            ->assertSee(route('izin.proses-verifikasi-pimpinan', $izin->uuid), false)
            ->assertSee('name="verifikasi_pimpinan"', false);
    }

    public function test_pimpinan_verification_page_is_forbidden_before_atasan_approval(): void
    {
        $owner = $this->createUserWithRole('user');
        $pimpinan = $this->createUserWithRole('pimpinan');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $pimpinanPegawai = Pegawai::where('nip', $pimpinan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'pimpinan_uuid' => $pimpinanPegawai->uuid,
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
        ]);

        $this->actingAs($pimpinan)
            ->get(route('izin.verifikasi-pimpinan', $izin->uuid))
            ->assertForbidden();
    }
}
