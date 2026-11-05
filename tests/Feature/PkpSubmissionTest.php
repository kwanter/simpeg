<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpSubmissionTest extends SimpegTestCase
{
    public function test_submission_storage_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('pkp_submissions'));
        $this->assertTrue(Schema::hasTable('pkp_events'));
    }

    public function test_bootstrap_submission_http_contract(): void
    {
        $this->travelTo(now('Asia/Makassar')->setDate(2030, 2, 10)->setTime(12, 0, 0));
        $owner = $this->createUserWithRole('user');
        $admin = $this->adminWithoutPegawai();
        $assessorUser = User::factory()->create();
        $assessor = Pegawai::factory()->create(['user_uuid' => $assessorUser->uuid]);
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->uuid,
            [$assessor->uuid]
        );
        $jabatan = Jabatan::factory()->create(['nama' => 'Hakim Madya']);
        $this->createIdentityHistories($owner->pegawai, $jabatan);

        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => [[
                'title' => 'Kinerja',
                'activities' => [[
                    'description' => 'Menyelesaikan target kerja',
                    'unit' => 'dokumen',
                    'target_credit' => '1.000000',
                    'target_quantity' => '3.0000',
                    'target_quality' => '100.0000',
                    'realized_quantity' => '2.0000',
                ]],
            ]],
        ])->assertOk();

        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk()
            ->assertJsonPath('data.state', 'submitted')
            ->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.lateness', 'deadline_not_set')
            ->assertJsonStructure(['data' => ['submission_uuid']]);

        $submissionUuid = $submitted->json('data.submission_uuid');
        $submission = DB::table('pkp_submissions')->where('uuid', $submissionUuid)->first();
        $identity = json_decode($submission->identity_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $scores = json_decode($submission->score_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('submitted', $submission->state);
        $this->assertSame(1, (int) $submission->number);
        $this->assertSame('2030-02-28', $identity['period_end']);
        $this->assertSame('IV/a', $identity['rank']['value']);
        $this->assertSame('Hakim Madya', $identity['position']['value']);
        $this->assertSame('PN Contoh', $identity['work_unit']['value']);
        $this->assertSame('pkp-v1', $submission->calculation_version);
        $this->assertSame('200/3', $scores['overall_score']['numerator'].'/'.$scores['overall_score']['denominator']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $submission->payload_sha256);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $submission->identity_sha256);
        $this->assertDatabaseHas('pkp_events', ['pkp_uuid' => $pkpUuid, 'event_type' => 'submitted']);
    }

    public function test_missing_identity_history_keeps_draft_unsubmitted(): void
    {
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessorSettings();
        [$pkpUuid, $revisionUuid] = $this->createCompleteDraft($owner, 2030, 2);

        $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['identity.rank', 'identity.position', 'identity.work_unit']);

        $this->assertDatabaseHas('pkp', [
            'uuid' => $pkpUuid,
            'version' => 2,
            'first_submitted_at' => null,
            'lateness' => null,
        ]);
        $this->assertDatabaseCount('pkp_submissions', 0);
        $this->assertDatabaseCount('pkp_events', 0);
    }

    public function test_future_period_cannot_submit_even_with_complete_history(): void
    {
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessorSettings();
        [$pkpUuid, $revisionUuid] = $this->createCompleteDraft($owner, 2030, 3);

        $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $this->assertDatabaseHas('pkp', [
            'uuid' => $pkpUuid,
            'version' => 2,
            'first_submitted_at' => null,
        ]);
        $this->assertDatabaseCount('pkp_submissions', 0);
    }

    private function adminWithoutPegawai(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(\App\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(\App\Models\Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        return $admin;
    }

    /** @return array{User, Pegawai} */
    private function createOwnerAndAssessorSettings(): array
    {
        $owner = $this->createUserWithRole('user');
        $admin = $this->adminWithoutPegawai();
        $assessorUser = User::factory()->create();
        $assessor = Pegawai::factory()->create(['user_uuid' => $assessorUser->uuid]);
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->uuid,
            [$assessor->uuid]
        );

        return [$owner, $assessor];
    }

    /** @return array{string, string} */
    private function createCompleteDraft(User $owner, int $year, int $month): array
    {
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => $year,
            'month' => $month,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => [[
                'title' => 'Kinerja',
                'activities' => [[
                    'description' => 'Menyelesaikan target kerja',
                    'unit' => 'dokumen',
                    'target_credit' => '1.000000',
                    'target_quantity' => '3.0000',
                    'target_quality' => '100.0000',
                    'realized_quantity' => '2.0000',
                ]],
            ]],
        ])->assertOk();

        return [$pkpUuid, $revisionUuid];
    }

    private function createIdentityHistories(Pegawai $pegawai, Jabatan $jabatan): void
    {
        RiwayatPangkat::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'pangkat_golongan' => 'IV/a',
            'tmt' => '2029-01-01',
            'nomor_sk' => 'SK-RANK-1',
            'tanggal_sk' => '2029-01-01',
        ]);
        RiwayatJabatan::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jabatan_uuid' => $jabatan->uuid,
            'satuan_kerja' => 'PN Contoh',
            'tanggal_mulai' => '2029-01-01',
        ]);
    }
}
