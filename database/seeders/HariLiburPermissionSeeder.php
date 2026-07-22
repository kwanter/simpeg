<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
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
                ['name' => $permission, 'guard_name' => 'web'],
                ['uuid' => Str::uuid()->toString()]
            );
        }
    }
}
