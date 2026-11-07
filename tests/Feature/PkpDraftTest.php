<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use App\Services\PkpSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\SimpegTestCase;

class PkpDraftTest extends SimpegTestCase
{
    /** @dataProvider draftSources */
    public function test_owner_can_complete_unset_assessor_and_notes_in_each_draft_source(string $source): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, $source);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'assessor_pegawai_uuid' => null]);
        $admin = $this->adminWithoutPegawai();
        $default = $this->eligibleAssessor();
        $alternative = $this->eligibleAssessor();
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 0, $default->uuid, [$default->uuid, $alternative->uuid]);
        $notes = 'Approved scope <script>unsafe</script> & details';
        $url = '/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid;

        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'notes' => $notes, 'assessor_pegawai_uuid' => $alternative->uuid])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => $notes, 'assessor_pegawai_uuid' => $alternative->uuid]);
        $this->actingAs($owner)->getJson($url.'/edit')->assertOk()
            ->assertJsonPath('data.notes', $notes)->assertJsonPath('data.assessor_pegawai_uuid', $alternative->uuid)
            ->assertJsonCount(2, 'data.assessor_options');
        $this->actingAs($owner)->get($url.'/edit')->assertOk()->assertSee('Catatan')
            ->assertSee('x-model="notes"', false)->assertSee('x-model="assessorUuid"', false)->assertSee($alternative->nama);
        $this->actingAs($owner)->putJson($url, ['expected_version' => 2])->assertOk()->assertJsonPath('data.version', 3);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => $notes, 'assessor_pegawai_uuid' => $alternative->uuid]);
        $this->actingAs($owner)->putJson($url, ['expected_version' => 2, 'notes' => 'stale notes'])->assertConflict();
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 3]);
    }

    public static function draftSources(): array
    {
        return [['application'], ['external']];
    }

    public function test_editable_form_exposes_notes_and_owner_assessor_choices(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');

        $this->actingAs($owner)->get('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid.'/edit')
            ->assertOk()->assertSee('x-model="notes"', false)->assertSee('x-model="assessorUuid"', false);
    }

    public function test_draft_assessor_selection_rechecks_revoked_and_ineligible_options_without_partial_save(): void
    {
        $owner = $this->createUserWithRole('user');
        $admin = $this->adminWithoutPegawai();
        $assessor = $this->eligibleAssessor();
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 0, $assessor->uuid, [$assessor->uuid]);
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        $url = '/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid;
        $this->actingAs($owner)->getJson('/pkp/my-assessor-options')->assertJsonPath('data.options.0.pegawai_uuid', $assessor->uuid);
        $replacement = $this->eligibleAssessor();
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 1, $replacement->uuid, [$replacement->uuid]);
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'notes' => 'must not persist', 'assessor_pegawai_uuid' => $assessor->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'notes' => 'must not persist'])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 2, $assessor->uuid, [$assessor->uuid]);
        $assessor->user->update(['status' => '0']);
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'notes' => 'inactive', 'assessor_pegawai_uuid' => $assessor->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $assessor->user->forceFill(['status' => '1', 'email_verified_at' => null])->save();
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'assessor_pegawai_uuid' => $assessor->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $assessor->user->forceFill(['email_verified_at' => now()])->save();
        $assessor->user->delete();
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'assessor_pegawai_uuid' => $assessor->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $assessor->user->restore();
        $assessor->delete();
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'assessor_pegawai_uuid' => $assessor->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'assessor_pegawai_uuid' => $owner->pegawai->uuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1]);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => null, 'assessor_pegawai_uuid' => $assessor->uuid]);
    }

    public function test_notes_length_null_and_foreign_owner_are_validated_without_overwriting(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'external');
        $url = '/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid;
        $notes = str_repeat('x', 5000);
        $this->actingAs($owner)->putJson($url, ['expected_version' => 1, 'notes' => $notes])->assertOk();
        $this->actingAs($owner)->putJson($url, ['expected_version' => 2, 'notes' => $notes.'x'])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $other = $this->createUserWithRole('user');
        $this->actingAs($other)->putJson($url, ['expected_version' => 2, 'notes' => 'foreign'])->assertForbidden();
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => $notes]);
        $this->actingAs($owner)->putJson($url, ['expected_version' => 2, 'notes' => null])->assertOk();
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'notes' => null]);
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 3]);
    }

    public function test_json_request_validation_returns_pkp_fields_without_creating_a_record(): void
    {
        $owner = $this->createUserWithRole('user');

        $this->actingAs($owner)->postJson('/pkp', ['year' => 2030, 'month' => 13, 'source' => 'application'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['message', 'fields' => ['month']], 'errors' => ['month']]);

        $this->assertDatabaseCount('pkp', 0);
    }

    public function test_html_request_validation_preserves_non_file_input(): void
    {
        $owner = $this->createUserWithRole('user');

        $this->actingAs($owner)->from('/pkp/create')->post('/pkp', ['year' => 2030, 'month' => 13, 'source' => 'external'])
            ->assertRedirect('/pkp/create')->assertSessionHasErrors('month')
            ->assertSessionHas('_old_input.source', 'external')->assertSessionHas('_old_input.year', 2030);

        $this->assertDatabaseCount('pkp', 0);
    }

    public function test_pkp_json_read_failures_have_safe_module_errors(): void
    {
        $unlinked = User::factory()->create();
        $this->actingAs($unlinked)->getJson('/pkp/create')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $owner = $this->createUserWithRole('user');
        $this->actingAs($owner)->getJson('/pkp/'.(string) Str::uuid())->assertNotFound()->assertJsonPath('error.code', 'resource_not_found');
    }

    public function test_draft_storage_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('pkp'));
        $this->assertTrue(Schema::hasTable('pkp_revisions'));
        $this->assertTrue(Schema::hasTable('pkp_deadlines'));
    }

    public function test_bootstrap_owned_draft_http_contract(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawaiUuid = $owner->pegawai->uuid;

        $this->actingAs($owner)
            ->postJson('/pkp', [
                'year' => 2030,
                'month' => 2,
                'source' => 'application',
            ])
            ->assertCreated()
            ->assertJsonPath('data.pegawai_uuid', $pegawaiUuid)
            ->assertJsonPath('data.year', 2030)
            ->assertJsonPath('data.month', 2)
            ->assertJsonPath('data.source', 'application')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.revision_number', 1)
            ->assertJsonPath('data.revision_state', 'draft');
    }

    public function test_monthly_list_defaults_to_owner_and_all_scope_requires_view_permission(): void
    {
        $owner = $this->createUserWithRole('user');
        $otherOwner = $this->createUserWithRole('user');
        $service = app(PkpSubmissionService::class);
        $first = $service->create($owner, 2030, 2, 'application');
        $second = $service->create($owner, 2030, 1, 'application');
        $foreign = $service->create($otherOwner, 2030, 3, 'application');

        $this->actingAs($owner)->getJson('/pkp?year=2030')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.pkp_uuid', $first->recordUuid)
            ->assertJsonPath('data.1.pkp_uuid', $second->recordUuid)
            ->assertJsonPath('data.0.pending_revision_uuid', $first->pendingRevisionUuid)
            ->assertJsonPath('data.0.content_approved', false)
            ->assertJsonPath('data.0.document_complete', false)
            ->assertJsonMissing(['pkp_uuid' => $foreign->recordUuid]);

        $this->actingAs($owner)->getJson('/pkp?scope=all&year=2030')->assertForbidden();

        $admin = $this->adminWithoutPegawai();
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.view-all', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        $this->actingAs($admin)->getJson('/pkp?scope=all&year=2030')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.pkp_uuid', $foreign->recordUuid);
    }

    public function test_monthly_list_rejects_invalid_year_and_page(): void
    {
        $owner = $this->createUserWithRole('user');

        $this->actingAs($owner)->getJson('/pkp?year=1899')->assertUnprocessable();
        $this->actingAs($owner)->getJson('/pkp?year=2030&page=0')->assertUnprocessable();
    }

    public function test_monthly_list_distinguishes_empty_from_incomplete_records(): void
    {
        $owner = $this->createUserWithRole('user');

        $this->actingAs($owner)->get('/pkp?year=2030')
            ->assertOk()
            ->assertSee('Belum ada PKP untuk tahun 2030.');

        app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');

        $this->actingAs($owner)->get('/pkp?year=2030')
            ->assertOk()
            ->assertSee('02/2030')
            ->assertSee('Belum disetujui')
            ->assertSee('Belum lengkap')
            ->assertDontSee('Belum ada PKP untuk tahun 2030.');
    }

    public function test_all_record_list_paginates_twenty_five_stably(): void
    {
        $owners = [
            $this->createUserWithRole('user'),
            $this->createUserWithRole('user'),
            $this->createUserWithRole('user'),
        ];
        $service = app(PkpSubmissionService::class);
        foreach (range(0, 25) as $index) {
            $service->create($owners[intdiv($index, 12)], 2030, ($index % 12) + 1, 'application');
        }

        $admin = $this->adminWithoutPegawai();
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.view-all', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        $this->actingAs($admin)->getJson('/pkp?scope=all&year=2030&page=1')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('total', 26)
            ->assertJsonPath('data.0.month', 12)
            ->assertJsonPath('data.24.month', 1);
        $this->actingAs($admin)->getJson('/pkp?scope=all&year=2030&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);
    }

    public function test_create_http_preselects_configured_assessor_and_returns_duplicate_link(): void
    {
        $owner = $this->createUserWithRole('user');
        $admin = $this->adminWithoutPegawai();
        $assessor = $this->eligibleAssessor();
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->uuid,
            [$assessor->uuid]
        );
        $payload = ['year' => 2030, 'month' => 2, 'source' => 'application'];

        $response = $this->actingAs($owner)->postJson('/pkp', $payload)
            ->assertCreated()
            ->assertJsonPath('data.revision_state', 'draft');
        $pkpUuid = $response->json('data.pkp_uuid');

        $this->assertDatabaseHas('pkp_revisions', [
            'pkp_uuid' => $pkpUuid,
            'assessor_pegawai_uuid' => $assessor->uuid,
        ]);

        $this->actingAs($owner)->postJson('/pkp', array_merge($payload, ['source' => 'external']))
            ->assertConflict()
            ->assertJsonPath('error.code', 'duplicate_period')
            ->assertJsonPath('error.existing_pkp_uuid', $pkpUuid);
        $this->assertDatabaseCount('pkp', 1);
        $this->assertDatabaseCount('pkp_revisions', 1);
    }

    public function test_create_service_persists_owned_future_draft_and_unset_sentinels(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawaiUuid = $owner->pegawai->uuid;

        $result = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');

        $this->assertSame(1, $result->version);
        $this->assertDatabaseHas('pkp', [
            'uuid' => $result->recordUuid,
            'pegawai_uuid' => $pegawaiUuid,
            'year' => 2030,
            'month' => 2,
            'version' => 1,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'official_revision_uuid' => null,
        ]);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $result->pendingRevisionUuid,
            'pkp_uuid' => $result->recordUuid,
            'number' => 1,
            'source' => 'application',
            'state' => 'draft',
        ]);
        $this->assertDatabaseHas('pkp_assessor_settings', [
            'pegawai_uuid' => $pegawaiUuid,
            'default_assessor_uuid' => null,
            'version' => 0,
        ]);
        $this->assertDatabaseHas('pkp_deadlines', [
            'year' => 2030,
            'month' => 2,
            'deadline_at' => null,
            'version' => 0,
        ]);
    }

    public function test_create_service_rejects_duplicate_period_without_extra_records(): void
    {
        $owner = $this->createUserWithRole('user');
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 2, 'application');

        try {
            $service->create($owner, 2030, 2, 'external');
            $this->fail('A second PKP for the same employee and month must conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('pkp', 1);
        $this->assertDatabaseCount('pkp_revisions', 1);
        $this->assertDatabaseCount('pkp_deadlines', 1);
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1]);
        $this->assertSame(0, DB::table('pkp_assessor_settings')->where('pegawai_uuid', $owner->pegawai->uuid)->value('version'));
    }

    public function test_unlinked_user_cannot_create_a_personal_draft(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        try {
            app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
            $this->fail('A personal draft requires the actor’s linked employee record.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('pkp', 0);
            $this->assertDatabaseCount('pkp_revisions', 0);
        }
    }

    public function test_owner_assessor_options_returns_only_the_owners_eligible_allowlist(): void
    {
        $owner = $this->createUserWithRole('user');
        $admin = $this->adminWithoutPegawai();
        $allowed = $this->eligibleAssessor();
        $notAllowed = $this->eligibleAssessor();

        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $allowed->uuid,
            [$allowed->uuid]
        );

        $this->actingAs($owner)->getJson('/pkp/my-assessor-options')
            ->assertOk()
            ->assertJsonPath('data.settings_version', 1)
            ->assertJsonPath('data.configured_default_uuid', $allowed->uuid)
            ->assertJsonPath('data.default_assessor_uuid', $allowed->uuid)
            ->assertJsonPath('data.options.0.pegawai_uuid', $allowed->uuid)
            ->assertJsonMissing(['pegawai_uuid' => $notAllowed->uuid]);
    }

    public function test_create_form_uses_makassar_month_and_contains_csrf_and_assessor_options(): void
    {
        $owner = $this->createUserWithRole('user');
        $this->travelTo(now('Asia/Makassar')->setDate(2030, 2, 3)->setTime(1, 2, 3));
        $assessor = $this->eligibleAssessor();
        $admin = $this->adminWithoutPegawai();
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->uuid,
            [$assessor->uuid]
        );

        $this->actingAs($owner)->get('/pkp/create')
            ->assertOk()
            ->assertSee('csrf-token')
            ->assertSee('name="_token"', false)
            ->assertSee('name="year" type="number" min="1900" max="9999" value="2030"', false)
            ->assertSee('name="month" type="number" min="1" max="12" value="2"', false)
            ->assertSee($assessor->nama);
    }

    public function test_create_http_denies_unlinked_user_and_owner_spoofing(): void
    {
        $unlinkedOwner = User::factory()->create();
        $unlinkedOwner->assignRole(Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $this->actingAs($unlinkedOwner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertForbidden();

        $owner = $this->createUserWithRole('user');
        $other = Pegawai::factory()->create();
        $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
            'pegawai_uuid' => $other->uuid,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('pkp', 0);
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
}
