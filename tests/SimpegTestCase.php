<?php

namespace Tests;

use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

abstract class SimpegTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedCutiPermissions();
    }

    protected function createUserWithRole(string $role, array $userAttributes = [], array $pegawaiAttributes = []): User
    {
        $nip = $userAttributes['nip'] ?? fake()->unique()->numerify('##################');

        $roleModel = Role::firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );

        $user = User::factory()->create(array_merge([
            'nip' => $nip,
            'status' => 'aktif',
        ], $userAttributes));

        $user->assignRole($roleModel);

        Pegawai::factory()->create(array_merge([
            'nip' => $user->nip,
        ], $pegawaiAttributes));

        return $user;
    }

    public function seedCutiPermissions(): void
    {
        foreach ([
            'create cuti',
            'update cuti',
            'delete cuti',
            'verifikasi cuti',
            'pimpinan cuti',
            'atasan pimpinan cuti',
            'view izin',
            'create izin',
            'update izin',
            'delete izin',
            'verifikasi izin',
            'view hari libur',
        ] as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
        }
    }
}
