<?php

namespace Tests\Feature;

use App\Models\Permission;
use Tests\SimpegTestCase;

class UserPrivilegeTest extends SimpegTestCase
{
    public function test_admin_cannot_edit_super_admin_account(): void
    {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = $this->createUserWithRole('super-admin');
        $permission = Permission::firstOrCreate(['name' => 'update user', 'guard_name' => 'web']);
        $admin->givePermissionTo($permission);

        $this->actingAs($admin)->get(route('users.edit', $superAdmin))->assertForbidden();
    }

    public function test_admin_cannot_update_super_admin_account(): void
    {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = $this->createUserWithRole('super-admin');
        $permission = Permission::firstOrCreate(['name' => 'update user', 'guard_name' => 'web']);
        $admin->givePermissionTo($permission);

        $this->actingAs($admin)->put(route('users.update', $superAdmin), [
            'name' => 'Changed',
            'email' => 'changed@example.com',
            'nip' => $superAdmin->nip,
            'roles' => ['admin'],
            'status' => '1',
        ])->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->hasRole('super-admin'));
    }

    public function test_admin_cannot_delete_super_admin_account(): void
    {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = $this->createUserWithRole('super-admin');
        $permission = Permission::firstOrCreate(['name' => 'delete user', 'guard_name' => 'web']);
        $admin->givePermissionTo($permission);

        $this->actingAs($admin)->delete(route('users.destroy', $superAdmin))->assertForbidden();
        $this->assertDatabaseHas('users', ['uuid' => $superAdmin->uuid]);
    }
}
