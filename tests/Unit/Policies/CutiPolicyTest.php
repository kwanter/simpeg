<?php

namespace Tests\Unit\Policies;

use App\Models\Cuti;
use App\Models\Pegawai;
use App\Models\User;
use App\Policies\CutiPolicy;
use Tests\SimpegTestCase;

class CutiPolicyTest extends SimpegTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCutiPermissions();
    }

    protected function createCutiForUser($user, $attributes = [])
    {
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        return Cuti::factory()->create(array_merge([
            'pegawai_uuid' => $pegawai->uuid,
        ], $attributes));
    }

    public function test_view_any_is_always_true(): void
    {
        $policy = new CutiPolicy;
        $user = User::factory()->create();
        $this->assertTrue($policy->viewAny($user));
    }

    public function test_view_allows_admin(): void
    {
        $policy = new CutiPolicy;
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai);
        $admin = $this->createUserWithRole('admin');
        $this->assertTrue($policy->view($admin, $cuti));
    }

    public function test_view_allows_pimpinan(): void
    {
        $policy = new CutiPolicy;
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai);
        $pimpinan = $this->createUserWithRole('pimpinan');
        $this->assertTrue($policy->view($pimpinan, $cuti));
    }

    public function test_view_allows_verifikator(): void
    {
        $policy = new CutiPolicy;
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai);
        $verifikator = $this->createUserWithRole('verifikator');
        $this->assertTrue($policy->view($verifikator, $cuti));
    }

    public function test_view_allows_atasan(): void
    {
        $policy = new CutiPolicy;
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai);
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $this->assertTrue($policy->view($atasan, $cuti));
    }

    public function test_view_nama_pegawai_allows_admin(): void
    {
        $policy = new CutiPolicy;
        $admin = $this->createUserWithRole('admin');
        $this->assertTrue($policy->viewNamaPegawai($admin));
    }

    public function test_view_nama_pegawai_allows_pimpinan(): void
    {
        $policy = new CutiPolicy;
        $pimpinan = $this->createUserWithRole('pimpinan');
        $this->assertTrue($policy->viewNamaPegawai($pimpinan));
    }

    public function test_view_nama_pegawai_allows_verifikator(): void
    {
        $policy = new CutiPolicy;
        $verifikator = $this->createUserWithRole('verifikator');
        $this->assertTrue($policy->viewNamaPegawai($verifikator));
    }

    public function test_update_denies_without_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user, ['status' => 'Pending']);
        $this->assertFalse($policy->update($user, $cuti));
    }

    public function test_update_denies_wrong_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('update cuti');
        $cuti = $this->createCutiForUser($user, ['status' => 'Disetujui']);
        $this->assertFalse($policy->update($user, $cuti));
    }

    public function test_delete_denies_without_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user, ['status' => 'Pending']);
        $this->assertFalse($policy->delete($user, $cuti));
    }

    public function test_delete_denies_wrong_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('delete cuti');
        $cuti = $this->createCutiForUser($user, ['status' => 'Disetujui']);
        $this->assertFalse($policy->delete($user, $cuti));
    }

    public function test_verify_denies_without_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user, ['status' => 'Pending']);
        $this->assertFalse($policy->verify($user, $cuti));
    }

    public function test_verify_denies_wrong_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('verifikasi cuti');
        $cuti = $this->createCutiForUser($user, ['status' => 'Disetujui']);
        $this->assertFalse($policy->verify($user, $cuti));
    }

    public function test_verify_pimpinan_denies_without_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user, ['status' => 'Disetujui Verifikator']);
        $this->assertFalse($policy->verifyPimpinan($user, $cuti));
    }

    public function test_verify_pimpinan_denies_wrong_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('pimpinan cuti');
        $cuti = $this->createCutiForUser($user, ['status' => 'Pending']);
        $this->assertFalse($policy->verifyPimpinan($user, $cuti));
    }

    public function test_verify_atasan_pimpinan_denies_without_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user, ['status' => 'Disetujui Pimpinan']);
        $this->assertFalse($policy->verifyAtasanPimpinan($user, $cuti));
    }

    public function test_verify_atasan_pimpinan_denies_wrong_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('atasan pimpinan cuti');
        $cuti = $this->createCutiForUser($user, ['status' => 'Pending']);
        $this->assertFalse($policy->verifyAtasanPimpinan($user, $cuti));
    }

    public function test_view_allows_owner(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user);

        $this->assertTrue($policy->view($user, $cuti));
    }

    public function test_view_denies_other_pegawai(): void
    {
        $policy = new CutiPolicy;
        $user1 = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user1);

        $user2 = $this->createUserWithRole('pegawai');
        $this->assertFalse($policy->view($user2, $cuti));
    }

    public function test_view_nama_pegawai_allows_authorized_roles(): void
    {
        $policy = new CutiPolicy;
        $admin = $this->createUserWithRole('admin');
        $pimpinan = $this->createUserWithRole('pimpinan');
        $verifikator = $this->createUserWithRole('verifikator');

        $this->assertTrue($policy->viewNamaPegawai($admin));
        $this->assertTrue($policy->viewNamaPegawai($pimpinan));
        $this->assertTrue($policy->viewNamaPegawai($verifikator));
    }

    public function test_view_nama_pegawai_denies_atasan_and_pegawai(): void
    {
        $policy = new CutiPolicy;
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $pegawai = $this->createUserWithRole('pegawai');

        $this->assertFalse($policy->viewNamaPegawai($atasan));
        $this->assertFalse($policy->viewNamaPegawai($pegawai));
    }

    public function test_create_checks_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');

        $this->assertFalse($policy->create($user));

        $user->givePermissionTo('create cuti');
        $this->assertTrue($policy->create($user));
    }

    public function test_update_checks_permission_and_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('update cuti');

        $cutiPending = $this->createCutiForUser($user, ['status' => 'Pending']);
        $cutiApproved = $this->createCutiForUser($user, ['status' => 'Disetujui']);

        $this->assertTrue($policy->update($user, $cutiPending));
        $this->assertFalse($policy->update($user, $cutiApproved));

        $user->revokePermissionTo('update cuti');
        $this->assertFalse($policy->update($user, $cutiPending));
    }

    public function test_delete_checks_permission_and_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('delete cuti');

        $cutiPending = $this->createCutiForUser($user, ['status' => 'Pending']);
        $cutiApproved = $this->createCutiForUser($user, ['status' => 'Disetujui']);

        $this->assertTrue($policy->delete($user, $cutiPending));
        $this->assertFalse($policy->delete($user, $cutiApproved));

        $user->revokePermissionTo('delete cuti');
        $this->assertFalse($policy->delete($user, $cutiPending));
    }

    public function test_verify_checks_permission_and_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('verifikasi cuti');

        $cutiPending = $this->createCutiForUser($user, ['status' => 'Pending']);
        $cutiApproved = $this->createCutiForUser($user, ['status' => 'Disetujui']);

        $this->assertTrue($policy->verify($user, $cutiPending));
        $this->assertFalse($policy->verify($user, $cutiApproved));
    }

    public function test_verify_pimpinan_checks_permission_and_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('pimpinan cuti');

        $cutiVerifikator = $this->createCutiForUser($user, ['status' => 'Disetujui Verifikator']);
        $cutiPending = $this->createCutiForUser($user, ['status' => 'Pending']);

        $this->assertTrue($policy->verifyPimpinan($user, $cutiVerifikator));
        $this->assertFalse($policy->verifyPimpinan($user, $cutiPending));
    }

    public function test_verify_atasan_pimpinan_checks_permission_and_status(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $user->givePermissionTo('atasan pimpinan cuti');

        $cutiPimpinan = $this->createCutiForUser($user, ['status' => 'Disetujui Pimpinan']);
        $cutiVerifikator = $this->createCutiForUser($user, ['status' => 'Disetujui Verifikator']);

        $this->assertTrue($policy->verifyAtasanPimpinan($user, $cutiPimpinan));
        $this->assertFalse($policy->verifyAtasanPimpinan($user, $cutiVerifikator));
    }

    public function test_edit_no_surat_checks_role_and_status(): void
    {
        $policy = new CutiPolicy;
        $admin = $this->createUserWithRole('admin');
        $pegawai = $this->createUserWithRole('pegawai');

        $cutiPimpinan = $this->createCutiForUser($pegawai, ['status' => 'Disetujui Pimpinan']);
        $cutiPending = $this->createCutiForUser($pegawai, ['status' => 'Pending']);

        $this->assertTrue($policy->editNoSurat($admin, $cutiPimpinan));
        $this->assertFalse($policy->editNoSurat($admin, $cutiPending));
        $this->assertFalse($policy->editNoSurat($pegawai, $cutiPimpinan));
    }

    public function test_cetak_checks_status_and_no_surat(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');

        $cutiOk = $this->createCutiForUser($user, ['status' => 'Disetujui Pimpinan', 'no_surat_cuti' => '123']);
        $cutiNoSurat = $this->createCutiForUser($user, ['status' => 'Disetujui Pimpinan', 'no_surat_cuti' => '']);
        $cutiStatus = $this->createCutiForUser($user, ['status' => 'Pending', 'no_surat_cuti' => '123']);

        $this->assertTrue($policy->cetak($user, $cutiOk));
        $this->assertFalse($policy->cetak($user, $cutiNoSurat));
        $this->assertFalse($policy->cetak($user, $cutiStatus));
    }

    public function test_update_all_balances_checks_permission(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');

        $this->assertFalse($policy->updateAllBalances($user));
        $user->givePermissionTo('update cuti');
        $this->assertTrue($policy->updateAllBalances($user));
    }

    public function test_restore_and_force_delete_allow_admin_only(): void
    {
        $policy = new CutiPolicy;
        $admin = $this->createUserWithRole('admin');
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai);

        $this->assertTrue($policy->restore($admin, $cuti));
        $this->assertTrue($policy->forceDelete($admin, $cuti));

        $this->assertFalse($policy->restore($pegawai, $cuti));
        $this->assertFalse($policy->forceDelete($pegawai, $cuti));
    }

    public function test_super_admin_bypasses_all_via_gate(): void
    {
        $superAdmin = $this->createUserWithRole('super-admin');
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai, ['status' => 'Pending']);

        $this->assertTrue($superAdmin->can('view', $cuti));
        $this->assertTrue($superAdmin->can('update', $cuti));
        $this->assertTrue($superAdmin->can('delete', $cuti));
        $this->assertTrue($superAdmin->can('verify', $cuti));

        $cutiVerifikator = $this->createCutiForUser($pegawai, ['status' => 'Disetujui Verifikator']);
        $this->assertTrue($superAdmin->can('verifyPimpinan', $cutiVerifikator));

        $this->assertTrue($superAdmin->can('restore', $cuti));
        $this->assertTrue($superAdmin->can('forceDelete', $cuti));
    }

    public function test_view_denies_if_pegawai_not_found(): void
    {
        $policy = new CutiPolicy;
        $user = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($user);

        $cuti->setRelation('pegawai', null);

        $otherUser = $this->createUserWithRole('pegawai');
        $this->assertFalse($policy->view($otherUser, $cuti));
    }

    public function test_view_allows_authorized_role_even_if_pegawai_missing(): void
    {
        $policy = new CutiPolicy;
        $admin = $this->createUserWithRole('admin');
        $pegawai = $this->createUserWithRole('pegawai');
        $cuti = $this->createCutiForUser($pegawai);

        $cuti->setRelation('pegawai', null);

        $this->assertTrue($policy->view($admin, $cuti));
    }
}
