<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
//use Spatie\Permission\Models\Role;
//use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class UserRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the uuid column exists in the roles table
        if (!Schema::hasColumn('roles', 'uuid')) {
            throw new \Exception('The roles table is missing the uuid column. Please run the appropriate migration.');
        }

        // Ensure all existing roles have UUIDs
        Role::whereNull('uuid')->orderBy('uuid')->each(function ($role) {
            $role->uuid = Str::uuid()->toString();
            $role->save();
        });

        // Create Permissions
        $permissions = [
            'view role', 'create role', 'update role', 'delete role',
            'view permission', 'create permission', 'update permission', 'delete permission',
            'view user', 'create user', 'update user', 'delete user',
            'view pegawai', 'create pegawai', 'update pegawai', 'delete pegawai',
            'view pangkat', 'create pangkat', 'update pangkat', 'delete pangkat',
            'view jabatan', 'create jabatan', 'update jabatan', 'delete jabatan',
            'view riwayat_jabatan', 'create riwayat_jabatan', 'update riwayat_jabatan', 'delete riwayat_jabatan',
            'view cuti', 'create cuti', 'update cuti', 'verifikasi cuti', 'delete cuti',
            'view izin', 'create izin', 'update izin', 'verifikasi izin', 'delete izin',
            'verifikasi data',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ], [
                'uuid' => Str::uuid(),
            ]);
        }

        // Add this before giving permissions to roles
        $permissions = Permission::all();
        foreach ($permissions as $permission) {
            if (empty($permission->uuid)) {
                Log::error("Permission '{$permission->name}' has no UUID");
            }
        }

        // Create Roles
        $roles = ['super-admin', 'admin', 'pimpinan', 'verifikator', 'user'];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate([
                'uuid' => Str::uuid()->toString(),
                'name' => $roleName,
                'guard_name' => 'web'
            ]);
            Log::info("Created role: " . json_encode($role->toArray()));
        }

        // After creating roles
        Log::info("Roles created: " . json_encode(Role::all()->toArray()));

        // Let's give all permission to super-admin role.
        $allPermissionNames = Permission::pluck('name')->toArray();

        $superAdminRole = Role::where('name', 'super-admin')->first();
        if (!$superAdminRole) {
            throw new \Exception('Super-admin role not found');
        }

        Log::info('Super-admin role UUID: ' . $superAdminRole->uuid);

        if ($superAdminRole) {
            try {
                // Add this debug line
                Log::info("Super-admin role: " . json_encode($superAdminRole->toArray()));
                
                // Ensure the role has a UUID before assigning permissions
                if ($superAdminRole->uuid) {
                    // Before assigning permissions
                    Log::info("Permissions to be assigned: " . json_encode($allPermissionNames));
                    Log::info("Super-admin role before assignment: " . json_encode($superAdminRole->toArray()));

                    // Inside the try block, just before $superAdminRole->givePermissionTo($allPermissionNames);
                    Log::info("Attempting to assign permissions to super-admin role");
                    
                    $superAdminRole->givePermissionTo($allPermissionNames);
                } else {
                    Log::error("Super-admin role has no UUID");
                }
            } catch (\Exception $e) {
                Log::error("Failed to give permissions to super-admin: " . $e->getMessage());
                Log::error("Super-admin role UUID: " . $superAdminRole->uuid);
                Log::error("Permissions: " . implode(', ', $allPermissionNames));
                
                // Add these debug lines
                Log::error("All roles: " . json_encode(Role::all()->toArray()));
                Log::error("All permissions: " . json_encode(Permission::all()->toArray()));
                
                throw $e;
            }
        } else {
            Log::error("Super-admin role not found");
            
            // Add this debug line
            Log::error("All roles: " . json_encode(Role::all()->toArray()));
        }

        // After assigning permissions to the super-admin role
        $superAdminRole = Role::where('name', 'super-admin')->first();
        $assignedPermissions = $superAdminRole->permissions()->pluck('name')->toArray();
        Log::info("Assigned permissions to super-admin: " . json_encode($assignedPermissions));

        // Check if all expected permissions are assigned
        $missingPermissions = array_diff($allPermissionNames, $assignedPermissions);
        if (!empty($missingPermissions)) {
            Log::warning("Missing permissions for super-admin: " . json_encode($missingPermissions));
        }

        // Let's give few permissions to admin role.
        $adminRole = Role::where('name', 'admin')->first();
        $adminRole->givePermissionTo(['create user', 'view user', 'update user']);
        $adminRole->givePermissionTo(['create pegawai', 'view pegawai', 'update pegawai']);
        $adminRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti']);
        $adminRole->givePermissionTo(['create izin', 'view izin']);
        $adminRole->givePermissionTo(['create pangkat', 'view pangkat', 'update pangkat']);
        $adminRole->givePermissionTo(['create jabatan', 'view jabatan', 'update jabatan']);
        $adminRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan']);
        $adminRole->givePermissionTo(['verifikasi data']);

        $pimpinanRole = Role::where('name', 'pimpinan')->first();
        $pimpinanRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti','delete cuti']);
        $pimpinanRole->givePermissionTo(['create izin', 'view izin', 'update izin','delete izin']);
        $pimpinanRole->givePermissionTo(['view pegawai','update pegawai']);
        $pimpinanRole->givePermissionTo(['verifikasi cuti', 'verifikasi izin']);
        $pimpinanRole->givePermissionTo(['view pangkat']);
        $pimpinanRole->givePermissionTo(['view jabatan']);
        $pimpinanRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan', 'update riwayat_jabatan','delete riwayat_jabatan']);
        $pimpinanRole->givePermissionTo(['view pegawai','update pegawai']);
        $pimpinanRole->givePermissionTo(['verifikasi data']);

        $verifikatorRole = Role::where('name', 'verifikator')->first();
        $verifikatorRole->givePermissionTo(['create pegawai', 'view pegawai', 'update pegawai','delete pegawai']);
        $verifikatorRole->givePermissionTo(['create pangkat', 'view pangkat', 'update pangkat','delete pangkat']);
        $verifikatorRole->givePermissionTo(['verifikasi cuti', 'verifikasi izin']);
        $verifikatorRole->givePermissionTo(['create jabatan', 'view jabatan', 'update jabatan','delete jabatan']);
        $verifikatorRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan', 'update riwayat_jabatan','delete riwayat_jabatan']);
        $verifikatorRole->givePermissionTo(['create cuti', 'view cuti', 'update cuti','delete cuti']);
        $verifikatorRole->givePermissionTo(['create izin', 'view izin', 'update izin','delete izin']);
        $verifikatorRole->givePermissionTo(['verifikasi data']);

        $userRole = Role::where('name', 'user')->first();
        $userRole->givePermissionTo(['create cuti', 'view cuti','update cuti','delete cuti']);
        $userRole->givePermissionTo(['create izin', 'view izin','update izin','delete izin']);
        $userRole->givePermissionTo(['view pangkat']);
        $userRole->givePermissionTo(['view jabatan']);
        $userRole->givePermissionTo(['create riwayat_jabatan', 'view riwayat_jabatan','update riwayat_jabatan','delete riwayat_jabatan']);
        $userRole->givePermissionTo(['view pegawai','update pegawai']);

        // After assigning permissions to all roles
        $roles = ['admin', 'pimpinan', 'verifikator', 'user'];
        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $assignedPermissions = $role->permissions()->pluck('name')->toArray();
            Log::info("Assigned permissions to {$roleName}: " . json_encode($assignedPermissions));
        }

        // Let's Create User and assign Role to it.

        $superAdminUser = User::firstOrCreate([
                    'email' => 'pn.tanahgrogot@gmail.com',
                ], [
                    'uuid' => Str::uuid(),
                    'name' => 'Super Admin PN Tanah Grogot',
                    'password' => Hash::make('PN@grogotkelas2'),
                ]);

        $superAdminUser->assignRole($superAdminRole);

        $adminUser = User::firstOrCreate([
                            'email' => 'pn-tanahgrogot@yahoo.co.id'
                        ], [
                            'uuid' => Str::uuid(),
                            'name' => 'Admin PN Tanah Grogot',
                            'password' => Hash::make('pnkelas2'),
                        ]);

        $adminUser->assignRole($adminRole);

        $pimpinanUser = User::firstOrCreate([
            'email' => 'pimpinan@gmail.com',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Pimpinan PN Tanah Grogot',
            'password' => Hash::make('12345678'),
        ]);

        $pimpinanUser->assignRole($pimpinanRole);

        $verifikatorUser = User::firstOrCreate([
            'email' => 'verifikator@gmail.com',
        ], [
            'uuid' => Str::uuid(),
            'name' => 'Verifikator PN Tanah Grogot',
            'password' => Hash::make('12345678'),
        ]);

        $verifikatorUser->assignRole($verifikatorRole);

        $userUser = User::firstOrCreate([
                            'email' => 'user@gmail.com',
                        ], [
                            'uuid' => Str::uuid(),
                            'name' => 'Staff PN Tanah Grogot',
                            'password' => Hash::make('12345678'),
                        ]);

        $userUser->assignRole($userRole);

        // After creating and assigning roles to users
        $users = User::with('roles')->get();
        foreach ($users as $user) {
            Log::info("User {$user->name} (email: {$user->email}) has roles: " . $user->roles->pluck('name')->implode(', '));
        }

        // Final check
        $roleCount = Role::count();
        $permissionCount = Permission::count();
        $userCount = User::count();
        $roleHasPermissionsCount = DB::table('role_has_permissions')->count();
        $modelHasRolesCount = DB::table('model_has_roles')->count();

        Log::info("Seeding completed. Summary:");
        Log::info("- Roles created: {$roleCount}");
        Log::info("- Permissions created: {$permissionCount}");
        Log::info("- Users created: {$userCount}");
        Log::info("- Role-Permission associations: {$roleHasPermissionsCount}");
        Log::info("- User-Role associations: {$modelHasRolesCount}");

        if ($roleCount === 5 && $permissionCount === 36 && $userCount === 5) {
            Log::info("Seeding successful!");
        } else {
            Log::warning("Seeding may be incomplete. Please check the logs for any errors.");
        }
    }
}
