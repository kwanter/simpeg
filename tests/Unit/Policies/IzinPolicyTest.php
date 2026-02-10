<?php

namespace Tests\Unit\Policies;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\User;
use App\Policies\IzinPolicy;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class IzinPolicyTest extends SimpegTestCase
{
    protected function createIzinForUser($user, $attributes = [])
    {
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        return Izin::factory()->create(array_merge([
            'pegawai_uuid' => $pegawai->uuid,
            'uuid' => (string) Str::uuid(),
        ], $attributes));
    }

    public function test_view_any_is_always_true(): void
    {
        $policy = new IzinPolicy;
        $user = User::factory()->create();
        $this->assertTrue($policy->viewAny($user));
    }

    public function test_view_allows_admin(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);
        $admin = $this->createUserWithRole('admin');
        $this->assertTrue($policy->view($admin, $izin));
    }

    public function test_view_allows_pimpinan(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);
        $pimpinan = $this->createUserWithRole('pimpinan');
        $this->assertTrue($policy->view($pimpinan, $izin));
    }

    public function test_view_allows_atasan(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $this->assertTrue($policy->view($atasan, $izin));
    }

    public function test_view_nama_pegawai_allows_admin(): void
    {
        $policy = new IzinPolicy;
        $admin = $this->createUserWithRole('admin');
        $this->assertTrue($policy->viewNamaPegawai($admin));
    }

    public function test_view_nama_pegawai_allows_pimpinan(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $this->assertTrue($policy->viewNamaPegawai($pimpinan));
    }

    public function test_view_nama_pegawai_allows_atasan(): void
    {
        $policy = new IzinPolicy;
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $this->assertTrue($policy->viewNamaPegawai($atasan));
    }

    public function test_verify_atasan_denies_if_already_disetujui(): void
    {
        $policy = new IzinPolicy;
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['verifikasi_atasan' => 'Disetujui']);
        $this->assertFalse($policy->verifyAtasan($atasan, $izin));
    }

    public function test_verify_atasan_denies_if_already_ditolak(): void
    {
        $policy = new IzinPolicy;
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['verifikasi_atasan' => 'Ditolak']);
        $this->assertFalse($policy->verifyAtasan($atasan, $izin));
    }

    public function test_verify_pimpinan_denies_if_atasan_rejected(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Ditolak',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);
        $this->assertFalse($policy->verifyPimpinan($pimpinan, $izin));
    }

    public function test_verify_pimpinan_denies_if_pimpinan_already_disetujui(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Disetujui',
        ]);
        $this->assertFalse($policy->verifyPimpinan($pimpinan, $izin));
    }

    public function test_view_allows_owner(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);

        $this->assertTrue($policy->view($user, $izin));
    }

    public function test_view_denies_other_pegawai(): void
    {
        $policy = new IzinPolicy;
        $user1 = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user1);

        $user2 = $this->createUserWithRole('pegawai');
        $this->assertFalse($policy->view($user2, $izin));
    }

    public function test_view_nama_pegawai_allows_admin_pimpinan_atasan(): void
    {
        $policy = new IzinPolicy;
        $admin = $this->createUserWithRole('admin');
        $pimpinan = $this->createUserWithRole('pimpinan');
        $atasan = $this->createUserWithRole('atasan-pimpinan');

        $this->assertTrue($policy->viewNamaPegawai($admin));
        $this->assertTrue($policy->viewNamaPegawai($pimpinan));
        $this->assertTrue($policy->viewNamaPegawai($atasan));
    }

    public function test_view_nama_pegawai_denies_pegawai(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $this->assertFalse($policy->viewNamaPegawai($user));
    }

    public function test_create_denies_pimpinan_atasan(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $atasan = $this->createUserWithRole('atasan-pimpinan');

        $this->assertFalse($policy->create($pimpinan));
        $this->assertFalse($policy->create($atasan));
    }

    public function test_create_allows_admin_and_pegawai(): void
    {
        $policy = new IzinPolicy;
        $admin = $this->createUserWithRole('admin');
        $pegawai = $this->createUserWithRole('pegawai');

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->create($pegawai));
    }

    public function test_update_allows_admin(): void
    {
        $policy = new IzinPolicy;
        $admin = $this->createUserWithRole('admin');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Disetujui',
        ]);

        $this->assertTrue($policy->update($admin, $izin));
    }

    public function test_update_allows_owner_if_not_verified(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);

        $this->assertTrue($policy->update($user, $izin));
    }

    public function test_update_denies_owner_if_verified_by_atasan(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);

        $this->assertFalse($policy->update($user, $izin));
    }

    public function test_update_denies_owner_if_verified_by_pimpinan(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Disetujui',
        ]);

        $this->assertFalse($policy->update($user, $izin));
    }

    public function test_delete_delegates_to_update(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);

        $this->assertEquals($policy->update($user, $izin), $policy->delete($user, $izin));
    }

    public function test_verify_atasan_allows_atasan_and_admin_if_unverified(): void
    {
        $policy = new IzinPolicy;
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $admin = $this->createUserWithRole('admin');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['verifikasi_atasan' => 'Belum Diverifikasi']);

        $this->assertTrue($policy->verifyAtasan($atasan, $izin));
        $this->assertTrue($policy->verifyAtasan($admin, $izin));
    }

    public function test_verify_atasan_denies_if_already_verified(): void
    {
        $policy = new IzinPolicy;
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['verifikasi_atasan' => 'Disetujui']);

        $this->assertFalse($policy->verifyAtasan($atasan, $izin));
    }

    public function test_verify_atasan_denies_pimpinan_and_pegawai(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $pegawai = $this->createUserWithRole('pegawai');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['verifikasi_atasan' => 'Belum Diverifikasi']);

        $this->assertFalse($policy->verifyAtasan($pimpinan, $izin));
        $this->assertFalse($policy->verifyAtasan($pegawai, $izin));
    }

    public function test_verify_pimpinan_allows_pimpinan_and_admin_if_conditions_met(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $admin = $this->createUserWithRole('admin');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);

        $this->assertTrue($policy->verifyPimpinan($pimpinan, $izin));
        $this->assertTrue($policy->verifyPimpinan($admin, $izin));
    }

    public function test_verify_pimpinan_denies_if_atasan_not_approved(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);

        $this->assertFalse($policy->verifyPimpinan($pimpinan, $izin));
    }

    public function test_verify_pimpinan_denies_if_already_verified_by_pimpinan(): void
    {
        $policy = new IzinPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, [
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Disetujui',
        ]);

        $this->assertFalse($policy->verifyPimpinan($pimpinan, $izin));
    }

    public function test_cetak_allows_if_approved_and_has_no_surat(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');

        $izin1 = $this->createIzinForUser($user, ['status' => 'Disetujui', 'no_surat_izin' => '123']);
        $izin2 = $this->createIzinForUser($user, ['status' => 'Disetujui Atasan', 'no_surat_izin' => '123']);

        $this->assertTrue($policy->cetak($user, $izin1));
        $this->assertTrue($policy->cetak($user, $izin2));
    }

    public function test_cetak_denies_if_not_approved(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['status' => 'Pending', 'no_surat_izin' => '123']);

        $this->assertFalse($policy->cetak($user, $izin));
    }

    public function test_cetak_denies_if_no_surat_missing(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user, ['status' => 'Disetujui', 'no_surat_izin' => '']);

        $this->assertFalse($policy->cetak($user, $izin));
    }

    public function test_restore_and_force_delete_allow_admin_only(): void
    {
        $policy = new IzinPolicy;
        $admin = $this->createUserWithRole('admin');
        $pegawai = $this->createUserWithRole('pegawai');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);

        $this->assertTrue($policy->restore($admin, $izin));
        $this->assertTrue($policy->forceDelete($admin, $izin));

        $this->assertFalse($policy->restore($pegawai, $izin));
        $this->assertFalse($policy->forceDelete($pegawai, $izin));
    }

    public function test_super_admin_bypasses_all_via_gate(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);

        $this->assertTrue($superAdmin->can('view', $izin));
        $this->assertTrue($superAdmin->can('update', $izin));
        $this->assertTrue($superAdmin->can('delete', $izin));
        $this->assertTrue($superAdmin->can('verifyAtasan', $izin));
        $this->assertTrue($superAdmin->can('verifyPimpinan', $izin));
        $this->assertTrue($superAdmin->can('restore', $izin));
        $this->assertTrue($superAdmin->can('forceDelete', $izin));
    }

    public function test_view_denies_if_pegawai_not_found(): void
    {
        $policy = new IzinPolicy;
        $user = $this->createUserWithRole('pegawai');
        $izin = $this->createIzinForUser($user);

        $izin->setRelation('pegawai', null);

        $otherUser = $this->createUserWithRole('pegawai');
        $this->assertFalse($policy->view($otherUser, $izin));
    }
}
