<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class HariLiburPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions for hari libur
        $permissions = [
            'view hari libur',
            'create hari libur',
            'update hari libur',
            'delete hari libur',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                [
                    'uuid' => Str::uuid()->toString(),
                    'guard_name' => 'web'
                ]
            );
        }
    }
}