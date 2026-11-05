<?php

namespace Tests\Unit\Policies;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAuthorization;
use App\Support\PkpContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpAuthorizationTest extends SimpegTestCase
{
    public function test_unlinked_admin_with_explicit_permission_can_manage_assessor_settings(): void
    {
        $admin = $this->userWithRoleAndPermission('admin', 'pkp.manage-assessors');

        $this->assertNull($admin->pegawai);
        $this->assertTrue(app(PkpAuthorization::class)->allows(
            $admin,
            'manageAssessorSettings',
            new PkpContext
        ));
    }

    public function test_super_admin_without_module_permission_is_denied_despite_generic_gate_allow(): void
    {
        $superAdmin = $this->userWithRoleAndPermission('super-admin');
        $context = new PkpContext;

        $this->assertTrue(Gate::forUser($superAdmin)->allows('pkp.manage-assessors'));
        $this->assertFalse(app(PkpAuthorization::class)->allows(
            $superAdmin,
            'manageAssessorSettings',
            $context
        ));
    }

    public function test_inactive_admin_is_denied_even_with_module_permission(): void
    {
        $admin = $this->userWithRoleAndPermission('admin', 'pkp.manage-assessors', ['status' => '0']);

        $this->assertFalse(app(PkpAuthorization::class)->allows(
            $admin,
            'manageAssessorSettings',
            new PkpContext
        ));
    }

    private function userWithRoleAndPermission(string $role, ?string $permission = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        if ($permission !== null) {
            $user->givePermissionTo(Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid()]
            ));
        }

        return $user;
    }
}
