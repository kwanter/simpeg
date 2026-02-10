<?php

namespace Tests;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class SimpegTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $permissionRegistrar = $this->app->make(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();
        $permissionRegistrar->flushCache();
    }
        $this->app->make(PermissionRegistrar::class)->flushCache();
    }

    public function createUserWithRole(string $role, array $userAttributes = [], array $pegawaiAttributes = []): User
    {
        $roleModel = Role::firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );
        $roleModel->load('name', 'guard_name', 'uuid');
        $user = User::factory()->create(array_merge([
            'nip' => $nip,
            'status' => 'aktif',
        ], $userAttributes));
        $nip = fake()->unique()->numerify('##################');

        $user = User::factory()->create(array_merge([
            'nip' => $nip,
            'status' => 'aktif',
        ], $userAttributes));

        $user->assignRole($roleModel);

        Pegawai::factory()->create(array_merge([
            'nip' => $nip,
        ], $pegawaiAttributes));

        return $user;
    }

    public function seedCutiPermissions(): void
    {
        $permissions = [
            'create cuti',
            'update cuti',
            'delete cuti',
            'verifikasi cuti',
            'pimpinan cuti',
            'atasan pimpinan cuti',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
        }
    }

    protected function createUserWithRole(string $role, array $userAttributes = [], array $pegawaiAttributes = []): User
    {
        $roleModel = Role::firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );
        $roleModel->load('name', 'guard_name', 'uuid');
        $user = User::factory()->create(array_merge([
            'nip' => $nip,
            'status' => 'aktif',
        ], $userAttributes));
        
        $nip = fake()->unique()->numerify('##################');
        
        $user = User::factory()->create(array_merge([
            'nip' => $nip,
            'status' => 'aktif',
        ], $userAttributes));
        
        $user->assignRole($roleModel);
        
        Pegawai::factory()->create(array_merge([
            'nip' => $nip,
        ], $pegawaiAttributes));
        
        return $user;
    }
            'create cuti',
            'update cuti',
            'delete cuti',
            'verifikasi cuti',
            'pimpinan cuti',
            'atasan pimpinan cuti',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
        }
    }
}
