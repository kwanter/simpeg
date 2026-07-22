<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\SimpegTestCase;

class UserProvisioningTest extends SimpegTestCase
{
    public function test_admin_provisioned_user_is_marked_verified(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'create user', 'guard_name' => 'web']));
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $pegawai = Pegawai::factory()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'nip' => $pegawai->nip,
            'password' => 'StrongPassword!123',
            'roles' => [$role->name],
        ])->assertRedirect('/users');

        $user = User::where('email', 'new.user@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
    }
}
