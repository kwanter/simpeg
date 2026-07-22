<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('roles', 'uuid')) {
            throw new \Exception('The roles table is missing the uuid column. Please run the appropriate migration.');
        }

        Role::whereNull('uuid')->each(function ($role) {
            $role->uuid = Str::uuid()->toString();
            $role->save();
        });

        $permissions = [
            'view role', 'create role', 'update role', 'delete role',
            'view permission', 'create permission', 'update permission', 'delete permission',
            'view user', 'create user', 'update user', 'delete user',
            'view pegawai', 'create pegawai', 'update pegawai', 'delete pegawai', 'detail pegawai',
            'view pangkat', 'create pangkat', 'update pangkat', 'delete pangkat',
            'view jabatan', 'create jabatan', 'update jabatan', 'delete jabatan',
            'view riwayat_jabatan', 'create riwayat_jabatan', 'update riwayat_jabatan', 'delete riwayat_jabatan',
            'view riwayat_pangkat', 'create riwayat_pangkat', 'update riwayat_pangkat', 'delete riwayat_pangkat',
            'view cuti', 'create cuti', 'update cuti', 'verifikasi cuti', 'delete cuti', 'pimpinan cuti', 'atasan pimpinan cuti', 'proses-verifikasi-atasan-pimpinan cuti', 'verifikasi-pimpinan cuti', 'proses-verifikasi-pimpinan cuti',
            'view izin', 'create izin', 'update izin', 'verifikasi izin', 'delete izin',
            'verifikasi data',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'uuid' => Str::uuid(),
            ]);
        }

        $roles = ['super-admin', 'admin', 'pimpinan', 'verifikator', 'user', 'atasan-pimpinan'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['uuid' => Str::uuid()->toString()]
            );
        }

        $allPermissionNames = Permission::pluck('name')->toArray();

        $superAdminRole = Role::where('name', 'super-admin')->first();
        if (! $superAdminRole) {
            throw new \Exception('Super-admin role not found');
        }

        if ($superAdminRole->uuid) {
            $superAdminRole->givePermissionTo($allPermissionNames);
        }

        $adminRole = Role::where('name', 'admin')->first();
        $adminRole->givePermissionTo(['create user', 'view user', 'update user']);
        $adminRole->givePermissionTo(['create pegawai', 'view pegawai', 'update pegawai', 'detail pegawai']);
        $adminRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti', 'delete cuti', 'pimpinan cuti']);
        $adminRole->givePermissionTo(['create izin', 'view izin']);
        $adminRole->givePermissionTo(['create pangkat', 'view pangkat', 'update pangkat']);
        $adminRole->givePermissionTo(['create jabatan', 'view jabatan', 'update jabatan']);
        $adminRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan']);
        $adminRole->givePermissionTo(['create riwayat_pangkat', 'view riwayat_pangkat']);
        $adminRole->givePermissionTo(['verifikasi data']);

        $atasanpimpinanRole = Role::where('name', 'atasan-pimpinan')->first();
        $atasanpimpinanRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti', 'delete cuti', 'pimpinan cuti', 'atasan pimpinan cuti', 'proses-verifikasi-atasan-pimpinan cuti', 'verifikasi-pimpinan cuti', 'proses-verifikasi-pimpinan cuti']);
        $atasanpimpinanRole->givePermissionTo(['create izin', 'view izin', 'update izin', 'delete izin']);
        $atasanpimpinanRole->givePermissionTo(['view pegawai', 'update pegawai', 'detail pegawai']);
        $atasanpimpinanRole->givePermissionTo(['verifikasi cuti', 'verifikasi izin']);
        $atasanpimpinanRole->givePermissionTo(['view pangkat']);
        $atasanpimpinanRole->givePermissionTo(['view jabatan']);
        $atasanpimpinanRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan', 'update riwayat_jabatan', 'delete riwayat_jabatan']);
        $atasanpimpinanRole->givePermissionTo(['create riwayat_pangkat', 'view riwayat_pangkat', 'update riwayat_pangkat', 'delete riwayat_pangkat']);
        $atasanpimpinanRole->givePermissionTo(['verifikasi data']);

        $pimpinanRole = Role::where('name', 'pimpinan')->first();
        $pimpinanRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti', 'delete cuti', 'pimpinan cuti']);
        $pimpinanRole->givePermissionTo(['create izin', 'view izin', 'update izin', 'delete izin']);
        $pimpinanRole->givePermissionTo(['view pegawai', 'update pegawai', 'detail pegawai']);
        $pimpinanRole->givePermissionTo(['verifikasi cuti', 'verifikasi izin']);
        $pimpinanRole->givePermissionTo(['view pangkat']);
        $pimpinanRole->givePermissionTo(['view jabatan']);
        $pimpinanRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan', 'update riwayat_jabatan', 'delete riwayat_jabatan']);
        $pimpinanRole->givePermissionTo(['create riwayat_pangkat', 'view riwayat_pangkat', 'update riwayat_pangkat', 'delete riwayat_pangkat']);
        $pimpinanRole->givePermissionTo(['verifikasi data']);

        $verifikatorRole = Role::where('name', 'verifikator')->first();
        $verifikatorRole->givePermissionTo(['create pegawai', 'view pegawai', 'update pegawai', 'delete pegawai', 'detail pegawai']);
        $verifikatorRole->givePermissionTo(['create pangkat', 'view pangkat', 'update pangkat', 'delete pangkat']);
        $verifikatorRole->givePermissionTo(['verifikasi cuti', 'verifikasi izin']);
        $verifikatorRole->givePermissionTo(['create jabatan', 'view jabatan', 'update jabatan', 'delete jabatan']);
        $verifikatorRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan', 'update riwayat_jabatan', 'delete riwayat_jabatan']);
        $verifikatorRole->givePermissionTo(['create riwayat_pangkat', 'view riwayat_pangkat', 'update riwayat_pangkat', 'delete riwayat_pangkat']);
        $verifikatorRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti', 'delete cuti']);
        $verifikatorRole->givePermissionTo(['create izin', 'view izin', 'update izin', 'delete izin']);
        $verifikatorRole->givePermissionTo(['verifikasi data']);

        $userRole = Role::where('name', 'user')->first();
        $userRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti', 'delete cuti']);
        $userRole->givePermissionTo(['create izin', 'view izin', 'update izin', 'delete izin']);
        $userRole->givePermissionTo(['view pangkat']);
        $userRole->givePermissionTo(['view jabatan']);
        $userRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan', 'update riwayat_jabatan', 'delete riwayat_jabatan']);
        $userRole->givePermissionTo(['create riwayat_pangkat', 'view riwayat_pangkat', 'update riwayat_pangkat', 'delete riwayat_pangkat']);
        $userRole->givePermissionTo(['view pegawai', 'update pegawai']);

        $plainPassword = config('app.seeder_default_password');
        if (config('app.env') === 'production' && (empty($plainPassword) || $plainPassword === 'ChangeMeImmediately!')) {
            throw new \RuntimeException('Set a strong SEEDER_DEFAULT_PASSWORD before seeding production.');
        }
        if (empty($plainPassword)) {
            $plainPassword = 'ChangeMeImmediately!';
        }
        $defaultPassword = Hash::make($plainPassword);
        $superAdminEmail = config('app.seeder_superadmin_email');
        if (! is_string($superAdminEmail)
            || preg_match('/[\r\n]/', $superAdminEmail)
            || ! filter_var($superAdminEmail, FILTER_VALIDATE_EMAIL)
        ) {
            throw new \RuntimeException('Set a valid SEEDER_SUPERADMIN_EMAIL before seeding.');
        }

        $superAdminUser = User::firstOrCreate([
            'email' => $superAdminEmail,
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Super Admin',
            'email_verified_at' => now(),
            'status' => '1',
            'password' => $defaultPassword,
        ]);

        $superAdminUser->assignRole($superAdminRole);

        // Production bootstraps only one super-admin; create other accounts through the UI.
        if (config('app.env') === 'production') {
            return;
        }

        $adminUser = User::firstOrCreate([
            'email' => 'admin@simpeg.local',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Admin',
            'email_verified_at' => now(),
            'status' => '1',
            'password' => $defaultPassword,
        ]);

        $adminUser->assignRole($adminRole);

        $atasanpimpinanUser = User::firstOrCreate([
            'email' => 'atasanpimpinan@simpeg.local',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Atasan Pimpinan',
            'email_verified_at' => now(),
            'status' => '1',
            'password' => $defaultPassword,
        ]);
        $atasanpimpinanUser->assignRole($atasanpimpinanRole);

        $pimpinanUser = User::firstOrCreate([
            'email' => 'pimpinan@simpeg.local',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Pimpinan',
            'email_verified_at' => now(),
            'status' => '1',
            'password' => $defaultPassword,
        ]);

        $pimpinanUser->assignRole($pimpinanRole);

        $verifikatorUser = User::firstOrCreate([
            'email' => 'verifikator@simpeg.local',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Verifikator',
            'email_verified_at' => now(),
            'status' => '1',
            'password' => $defaultPassword,
        ]);

        $verifikatorUser->assignRole($verifikatorRole);

        $userUser = User::firstOrCreate([
            'email' => 'user@simpeg.local',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Staff',
            'email_verified_at' => now(),
            'status' => '1',
            'password' => $defaultPassword,
        ]);

        $userUser->assignRole($userRole);
    }
}
