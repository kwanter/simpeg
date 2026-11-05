<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpNavigationTest extends SimpegTestCase
{
    public function test_linked_ordinary_owner_sees_pkp_navigation_outside_restricted_group(): void
    {
        $owner = $this->createUserWithRole('user');

        $this->actingAs($owner)->get('/pkp')
            ->assertOk()
            ->assertSee('href="'.route('pkp.index').'"', false)
            ->assertSee('PKP Bulanan')
            ->assertSee('href="'.route('pkp.create').'"', false);
    }

    public function test_unlinked_admin_with_view_all_permission_sees_all_pkp_navigation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.view-all', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-deadlines', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $target = Pegawai::factory()->create();

        $this->assertNull($admin->pegawai);
        $this->actingAs($admin)->get('/pkp/assessor-settings/'.$target->uuid)
            ->assertOk()
            ->assertSee('href="'.url('/pkp?scope=all').'"', false)
            ->assertSee('PKP Semua Pegawai')
            ->assertSee('href="'.route('pkp.deadline.show', [now('Asia/Makassar')->year, now('Asia/Makassar')->month]).'"', false)
            ->assertSee('Batas Waktu PKP');
    }
}
