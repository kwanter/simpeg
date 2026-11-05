<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use Database\Seeders\UserRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\SimpegTestCase;

class PkpAssessorSettingsTest extends SimpegTestCase
{
    public function test_assessor_settings_storage_has_a_unique_owner_and_audit_tables(): void
    {
        $this->assertTrue(Schema::hasTable('pkp_assessor_settings'));
        $this->assertTrue(Schema::hasTable('pkp_allowed_assessors'));
        $this->assertTrue(Schema::hasTable('pkp_configuration_events'));
    }

    public function test_user_role_permission_seeder_grants_pkp_configuration_permissions_to_admin(): void
    {
        config([
            'app.env' => 'testing',
            'app.seeder_default_password' => 'StrongSeederPassword!123',
            'app.seeder_superadmin_email' => 'pkp.superadmin@example.test',
        ]);

        $this->seed(UserRolePermissionSeeder::class);

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        foreach (['pkp.view-all', 'pkp.manage-assessors', 'pkp.manage-deadlines', 'pkp.reassign'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
            $this->assertTrue($adminRole->hasPermissionTo($permission));
        }
    }

    public function test_unlinked_admin_can_read_and_save_assessor_settings_over_http(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create();
        $firstAssessor = $this->eligibleAssessor();
        $secondAssessor = $this->eligibleAssessor();
        $url = '/pkp/assessor-settings/'.$owner->uuid;

        $this->actingAs($admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.settings_version', 0)
            ->assertJsonPath('data.configured_default_uuid', null)
            ->assertJsonPath('data.allowed_assessor_uuids', []);

        $this->actingAs($admin)->putJson($url, [
            'expected_version' => 0,
            'default_assessor_uuid' => $firstAssessor->uuid,
            'allowed_assessor_uuids' => [$firstAssessor->uuid, $secondAssessor->uuid],
        ])->assertOk()
            ->assertJsonPath('data.settings_version', 1)
            ->assertJsonPath('data.configured_default_uuid', $firstAssessor->uuid)
            ->assertJsonPath('data.allowed_assessor_uuids', [$firstAssessor->uuid, $secondAssessor->uuid]);

        $this->actingAs($admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.settings_version', 1)
            ->assertJsonPath('data.configured_default_uuid', $firstAssessor->uuid);
    }

    public function test_assessor_settings_http_routes_deny_missing_permission_and_foreign_owner(): void
    {
        $owner = Pegawai::factory()->create();
        $adminWithoutPermission = $this->userWithRole('admin');
        $this->actingAs($adminWithoutPermission)
            ->getJson('/pkp/assessor-settings/'.$owner->uuid)
            ->assertForbidden();

        $ordinaryOwner = $this->userWithRole('user');
        Pegawai::factory()->create(['user_uuid' => $ordinaryOwner->uuid]);
        $otherOwner = Pegawai::factory()->create();
        $this->actingAs($ordinaryOwner)
            ->getJson('/pkp/assessor-settings/'.$otherOwner->uuid)
            ->assertForbidden();
    }

    public function test_assessor_settings_web_form_renders_current_configuration(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create(['nama' => 'PKP Target']);
        $assessor = $this->eligibleAssessor();

        $this->actingAs($admin)
            ->get('/pkp/assessor-settings/'.$owner->uuid)
            ->assertOk()
            ->assertSee('Pengaturan Pejabat Penilai')
            ->assertSee('PKP Target')
            ->assertSee('allowed_assessor_uuids[]')
            ->assertSee($assessor->nama);
    }

    public function test_stale_assessor_settings_http_update_returns_conflict_without_mutation(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create();
        $firstAssessor = $this->eligibleAssessor();
        $secondAssessor = $this->eligibleAssessor();
        $url = '/pkp/assessor-settings/'.$owner->uuid;

        $this->actingAs($admin)->putJson($url, [
            'expected_version' => 0,
            'default_assessor_uuid' => $firstAssessor->uuid,
            'allowed_assessor_uuids' => [$firstAssessor->uuid],
        ])->assertOk();

        $this->actingAs($admin)->putJson($url, [
            'expected_version' => 0,
            'default_assessor_uuid' => $secondAssessor->uuid,
            'allowed_assessor_uuids' => [$secondAssessor->uuid],
        ])->assertConflict()
            ->assertJsonPath('error.code', 'stale_configuration');

        $this->assertDatabaseHas('pkp_assessor_settings', [
            'pegawai_uuid' => $owner->uuid,
            'default_assessor_uuid' => $firstAssessor->uuid,
            'version' => 1,
        ]);
        $this->assertDatabaseCount('pkp_configuration_events', 1);
    }

    public function test_assessor_settings_public_service_contract(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create();
        $assessor = $this->eligibleAssessor();

        $result = app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->uuid,
            0,
            $assessor->uuid,
            [$assessor->uuid]
        );

        $this->assertSame(1, $result->version);
        $this->assertSame($assessor->uuid, $result->defaultAssessorUuid);
        $this->assertSame([$assessor->uuid], $result->allowedAssessorUuids);
        $this->assertDatabaseHas('pkp_assessor_settings', [
            'uuid' => $result->configurationUuid,
            'pegawai_uuid' => $owner->uuid,
            'default_assessor_uuid' => $assessor->uuid,
            'version' => 1,
        ]);
        $this->assertDatabaseHas('pkp_configuration_events', [
            'kind' => 'assessor_settings',
            'configuration_uuid' => $result->configurationUuid,
            'actor_user_uuid' => $admin->uuid,
        ]);
        $this->assertSame(1, DB::table('pkp_allowed_assessors')
            ->where('settings_uuid', $result->configurationUuid)
            ->where('assessor_pegawai_uuid', $assessor->uuid)
            ->count());

        $event = DB::table('pkp_configuration_events')
            ->where('configuration_uuid', $result->configurationUuid)
            ->first();
        $this->assertNull($event->before_json);
        $this->assertSame($assessor->uuid, json_decode($event->after_json, true)['default_assessor_uuid']);
    }

    public function test_assessor_settings_rejects_default_outside_allowed_set(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create();
        $default = $this->eligibleAssessor();
        $allowed = $this->eligibleAssessor();

        try {
            app(PkpAssessorSettingsService::class)->configure(
                $admin,
                $owner->uuid,
                0,
                $default->uuid,
                [$allowed->uuid]
            );
            $this->fail('A default assessor outside the allowed set must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('default_assessor_uuid', $exception->errors());
        }

        $this->assertDatabaseCount('pkp_assessor_settings', 0);
        $this->assertDatabaseCount('pkp_configuration_events', 0);
    }

    public function test_assessor_settings_rejects_a_stale_zero_version_without_changing_configuration(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create();
        $firstAssessor = $this->eligibleAssessor();
        $secondAssessor = $this->eligibleAssessor();
        $service = app(PkpAssessorSettingsService::class);

        $created = $service->configure($admin, $owner->uuid, 0, $firstAssessor->uuid, [$firstAssessor->uuid]);

        try {
            $service->configure($admin, $owner->uuid, 0, $secondAssessor->uuid, [$secondAssessor->uuid]);
            $this->fail('A stale version zero must not update existing settings.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(1, $created->version);
        $this->assertDatabaseHas('pkp_assessor_settings', [
            'uuid' => $created->configurationUuid,
            'default_assessor_uuid' => $firstAssessor->uuid,
            'version' => 1,
        ]);
        $this->assertDatabaseCount('pkp_configuration_events', 1);
    }

    public function test_assessor_settings_rejects_self_selection_duplicates_and_inactive_accounts(): void
    {
        $admin = $this->adminWithoutPegawai();
        $owner = Pegawai::factory()->create();
        $assessor = $this->eligibleAssessor();
        $inactiveUser = User::factory()->create(['status' => '0']);
        $inactiveAssessor = Pegawai::factory()->create(['user_uuid' => $inactiveUser->uuid]);
        $service = app(PkpAssessorSettingsService::class);

        foreach ([
            [$owner->uuid, [$owner->uuid]],
            [$assessor->uuid, [$assessor->uuid, $assessor->uuid]],
            [$inactiveAssessor->uuid, [$inactiveAssessor->uuid]],
        ] as [$defaultUuid, $allowedUuids]) {
            try {
                $service->configure($admin, $owner->uuid, 0, $defaultUuid, $allowedUuids);
                $this->fail('Invalid assessor selections must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseCount('pkp_assessor_settings', 0);
        $this->assertDatabaseCount('pkp_configuration_events', 0);
    }

    public function test_bootstrap_empty_assessor_settings_http_contract(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        $target = Pegawai::factory()->create();

        $this->actingAs($admin)
            ->getJson('/pkp/assessor-settings/'.$target->uuid)
            ->assertOk()
            ->assertJsonPath('data.settings_version', 0)
            ->assertJsonPath('data.configured_default_uuid', null)
            ->assertJsonPath('data.allowed_assessor_uuids', []);
    }

    private function adminWithoutPegawai(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        return $admin;
    }

    private function eligibleAssessor(): Pegawai
    {
        $user = User::factory()->create();

        return Pegawai::factory()->create(['user_uuid' => $user->uuid]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        return $user;
    }
}
