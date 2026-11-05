<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PkpE2EFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('PKP browser fixtures can only be seeded in the testing environment.');
        }

        $ownerRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        );
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        );

        $permissions = [];
        foreach (['pkp.view-all', 'pkp.manage-assessors', 'pkp.manage-deadlines', 'pkp.reassign', 'create riwayat_jabatan', 'view riwayat_jabatan'] as $name) {
            $permissions[] = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid()]
            );
        }
        $adminRole->givePermissionTo($permissions);

        $password = Hash::make('pkp-testing-password');
        $owner = User::firstOrCreate(['email' => 'pkp-owner@example.test'], [
            'uuid' => (string) Str::uuid(),
            'name' => 'PKP Browser Owner',
            'email_verified_at' => now(),
            'password' => $password,
            'status' => '1',
            'nip' => 'PKP-E2E-OWNER',
        ]);
        $owner->syncRoles([$ownerRole]);
        Pegawai::firstOrCreate(['user_uuid' => $owner->uuid], [
            'uuid' => (string) Str::uuid(),
            'nip' => 'PKP-E2E-OWNER',
            'email' => $owner->email,
            'nama' => $owner->name,
        ]);
        $ownerPegawai = Pegawai::query()->where('user_uuid', $owner->uuid)->firstOrFail();

        $assessor = User::firstOrCreate(['email' => 'pkp-assessor@example.test'], [
            'uuid' => (string) Str::uuid(),
            'name' => 'PKP Browser Assessor',
            'email_verified_at' => now(),
            'password' => $password,
            'status' => '1',
            'nip' => 'PKP-E2E-ASSESSOR',
        ]);
        $assessor->syncRoles([$ownerRole]);
        $assessorPegawai = Pegawai::firstOrCreate(['user_uuid' => $assessor->uuid], [
            'uuid' => (string) Str::uuid(),
            'nip' => 'PKP-E2E-ASSESSOR',
            'email' => $assessor->email,
            'nama' => $assessor->name,
        ]);

        $replacement = User::firstOrCreate(['email' => 'pkp-replacement@example.test'], [
            'uuid' => (string) Str::uuid(),
            'name' => 'PKP Browser Replacement',
            'email_verified_at' => now(),
            'password' => $password,
            'status' => '1',
            'nip' => 'PKP-E2E-REPLACEMENT',
        ]);
        $replacement->syncRoles([$ownerRole]);
        $replacementPegawai = Pegawai::firstOrCreate(['user_uuid' => $replacement->uuid], [
            'uuid' => (string) Str::uuid(),
            'nip' => 'PKP-E2E-REPLACEMENT',
            'email' => $replacement->email,
            'nama' => $replacement->name,
        ]);

        $settingsUuid = (string) Str::uuid();
        DB::table('pkp_assessor_settings')->insertOrIgnore([
            'uuid' => $settingsUuid,
            'pegawai_uuid' => $ownerPegawai->uuid,
            'default_assessor_uuid' => $assessorPegawai->uuid,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $settingsUuid = DB::table('pkp_assessor_settings')
            ->where('pegawai_uuid', $ownerPegawai->uuid)
            ->value('uuid');
        DB::table('pkp_allowed_assessors')->insertOrIgnore([
            'settings_uuid' => $settingsUuid,
            'assessor_pegawai_uuid' => $assessorPegawai->uuid,
        ]);
        DB::table('pkp_allowed_assessors')->insertOrIgnore([
            'settings_uuid' => $settingsUuid,
            'assessor_pegawai_uuid' => $replacementPegawai->uuid,
        ]);

        $jabatan = Jabatan::firstOrCreate(['nama' => 'PKP E2E Hakim'], [
            'uuid' => (string) Str::uuid(),
            'deskripsi' => 'Browser test fixture',
            'parent_uuid' => null,
        ]);
        RiwayatPangkat::firstOrCreate([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'tmt' => '2020-01-01',
        ], [
            'uuid' => (string) Str::uuid(),
            'pangkat_golongan' => 'IV/a',
            'nomor_sk' => 'PKP-E2E-RANK',
            'tanggal_sk' => '2020-01-01',
        ]);
        RiwayatJabatan::firstOrCreate([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'tanggal_mulai' => '2020-01-01',
        ], [
            'uuid' => (string) Str::uuid(),
            'jabatan_uuid' => $jabatan->uuid,
            'satuan_kerja' => 'PN Browser Test',
        ]);

        $admin = User::firstOrCreate(['email' => 'pkp-admin@example.test'], [
            'uuid' => (string) Str::uuid(),
            'name' => 'PKP Browser Admin',
            'email_verified_at' => now(),
            'password' => $password,
            'status' => '1',
            'nip' => 'PKP-E2E-ADMIN',
        ]);
        $admin->syncRoles([$adminRole]);
    }
}
