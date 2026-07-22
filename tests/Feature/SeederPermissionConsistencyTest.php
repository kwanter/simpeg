<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\SimpegTestCase;

class SeederPermissionConsistencyTest extends SimpegTestCase
{
    public function test_seeder_defines_controller_permissions(): void
    {
        config(['app.env' => 'testing']);
        putenv('SEEDER_DEFAULT_PASSWORD=StrongSeederPassword!123');
        $_ENV['SEEDER_DEFAULT_PASSWORD'] = 'StrongSeederPassword!123';

        $this->seed(UserRolePermissionSeeder::class);

        foreach ([
            'detail pegawai',
            'view riwayat_jabatan',
            'create riwayat_jabatan',
            'update riwayat_jabatan',
            'delete riwayat_jabatan',
            'view riwayat_pangkat',
            'create riwayat_pangkat',
            'update riwayat_pangkat',
            'delete riwayat_pangkat',
        ] as $permission) {
            $this->assertTrue(Permission::where('name', $permission)->exists(), "Missing permission: {$permission}");
        }

        Artisan::call('permission:cache-reset');
    }

    public function test_production_seeder_creates_only_one_bootstrap_user_and_is_idempotent(): void
    {
        config([
            'app.env' => 'production',
            'app.seeder_default_password' => 'StrongSeederPassword!123',
            'app.seeder_superadmin_email' => 'official.admin@example.go.id',
        ]);

        $this->seed(UserRolePermissionSeeder::class);
        $this->seed(UserRolePermissionSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('users', ['email' => 'official.admin@example.go.id']);
        $this->assertSame(6, Role::count());
    }
}
