<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class HariLiburRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assign hari libur permissions to roles

        // Super Admin gets all permissions
        $superAdminRole = Role::where('name', 'super-admin')->first();

        // Admin gets all permissions
        $adminRole = Role::where('name', 'admin')->first();

        // Pimpinan gets view permission
        $pimpinanRole = Role::where('name', 'pimpinan')->first();

        // Verifikator gets view permission
        $verifikatorRole = Role::where('name', 'verifikator')->first();

        // Get all hari libur permissions
        $permissions = Permission::whereIn('name', [
            'view hari libur',
            'create hari libur',
            'update hari libur',
            'delete hari libur',
        ])->get();

        // Assign all permissions to super-admin and admin
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permissions);
        }

        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        // Assign only view permission to pimpinan and verifikator
        if ($pimpinanRole) {
            $pimpinanRole->givePermissionTo('view hari libur');
        }

        if ($verifikatorRole) {
            $verifikatorRole->givePermissionTo('view hari libur');
        }
    }
}