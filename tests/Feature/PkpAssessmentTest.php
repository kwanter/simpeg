<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessmentService;
use App\Services\PkpAssessorSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SimpegTestCase;

class PkpAssessmentTest extends SimpegTestCase
{
    /** @dataProvider queueKinds */
    public function test_empty_assigned_queue_preserves_legacy_rows_and_exposes_empty_page_metadata(string $kind): void
    {
        $actor = $this->createUserWithRole('user');
        $this->actingAs($actor)->getJson('/pkp/assessments?kind='.$kind)->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)->assertJsonPath('meta.per_page', 25)->assertJsonPath('links.next', null);
        $this->assertSame([], app(PkpAssessmentService::class)->queue($actor, $kind));
    }

    public function test_queue_uses_descending_uuid_to_break_equal_period_ties(): void
    {
        $first = $this->createSubmittedCase();
        $second = $this->createSubmittedCase();
        $admin = $this->adminWithoutPegawai();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'pkp.reassign', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        app(PkpAssessorSettingsService::class)->configure($admin, $second['owner']->pegawai->uuid, 1, $first['assessor']->pegawai->uuid, [$first['assessor']->pegawai->uuid, $second['assessor']->pegawai->uuid]);
        app(\App\Services\PkpAssignmentService::class)->reassignContent($admin, $second['pkp_uuid'], $second['revision_uuid'], 3, $first['assessor']->pegawai->uuid, 'The current assessor is unavailable.');
        $expected = [$first['pkp_uuid'], $second['pkp_uuid']];
        rsort($expected, SORT_STRING);

        $response = $this->actingAs($first['assessor'])->getJson('/pkp/assessments?kind=content')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame($expected, array_column($response->json('data'), 'pkp_uuid'));
    }

    /** @dataProvider invalidQueueFilters */
    public function test_queue_page_service_rejects_invalid_filters(string $kind, int $page, string $field): void
    {
        $actor = $this->createUserWithRole('user');
        try {
            app(PkpAssessmentService::class)->queuePage($actor, $kind, $page);
            $this->fail('Invalid queue filters must not return a successful paginator.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function invalidQueueFilters(): array
    {
        return [['content', 0, 'page'], ['document', -1, 'page'], ['invalid', 1, 'kind']];
    }

    /** @dataProvider queueKinds */
    public function test_assigned_queues_paginate_twenty_five_rows_and_validate_page(string $kind): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $case = $this->createSubmittedCase();
        $this->travelTo(Carbon::create(2035, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $cases = [$case];
        $service = app(\App\Services\PkpSubmissionService::class);
        $period = Carbon::create(2034, 12, 1);
        for ($index = 0; $index < 26; $index++) {
            $created = $service->create($case['owner'], $period->year, $period->month, 'application');
            $service->save($case['owner'], $created->recordUuid, $created->pendingRevisionUuid, 1, ['indicators' => [['title' => 'Kinerja', 'activities' => [[
                'description' => 'Queue fixture', 'unit' => 'dokumen', 'target_credit' => '1', 'target_quantity' => '3', 'target_quality' => '100', 'realized_quantity' => '2',
            ]]]]]);
            $submitted = $service->submit($case['owner'], $created->recordUuid, $created->pendingRevisionUuid, 2, now('UTC')->toDateTimeImmutable());
            $cases[] = ['owner' => $case['owner'], 'assessor' => $case['assessor'], 'pkp_uuid' => $created->recordUuid, 'revision_uuid' => $created->pendingRevisionUuid, 'submission_uuid' => $submitted->submissionUuid];
            $period->subMonthNoOverflow();
        }
        if ($kind === 'document') {
            $bytes = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>Queue PDF evidence</p>')->output();
            foreach ($cases as $item) {
                app(PkpAssessmentService::class)->decide($item['assessor'], $item['pkp_uuid'], $item['revision_uuid'], $item['submission_uuid'], 3, 'approve', null, now('UTC')->toDateTimeImmutable());
                app(\App\Services\PkpDocumentService::class)->store($item['owner'], $item['pkp_uuid'], $item['revision_uuid'], 4, 'signed_final', \Illuminate\Http\UploadedFile::fake()->createWithContent('queued.pdf', $bytes));
            }
        }
        $foreign = $this->createSubmittedCase();
        if ($kind === 'document') {
            app(PkpAssessmentService::class)->decide($foreign['assessor'], $foreign['pkp_uuid'], $foreign['revision_uuid'], $foreign['submission_uuid'], 3, 'approve', null, now('UTC')->toDateTimeImmutable());
            app(\App\Services\PkpDocumentService::class)->store($foreign['owner'], $foreign['pkp_uuid'], $foreign['revision_uuid'], 4, 'signed_final', \Illuminate\Http\UploadedFile::fake()->createWithContent('foreign.pdf', $bytes));
        }
        $first = $this->actingAs($case['assessor'])->getJson('/pkp/assessments?kind='.$kind)->assertOk()
            ->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 27)->assertJsonPath('meta.per_page', 25)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.pkp_uuid', $cases[1]['pkp_uuid'])->assertJsonMissing(['pkp_uuid' => $foreign['pkp_uuid']]);
        $second = $this->actingAs($case['assessor'])->getJson('/pkp/assessments?kind='.$kind.'&page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.pkp_uuid', $cases[26]['pkp_uuid'])->assertJsonPath('data.1.pkp_uuid', $case['pkp_uuid']);
        $ids = array_merge(array_column($first->json('data'), 'pkp_uuid'), array_column($second->json('data'), 'pkp_uuid'));
        $this->assertCount(27, array_unique($ids));
        $this->actingAs($case['assessor'])->getJson('/pkp/assessments?kind='.$kind.'&page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->actingAs($case['assessor'])->withHeader('Accept', 'text/html')->get('/pkp/assessments?kind='.$kind)->assertOk()->assertSee('page=2', false)->assertSee('revision_uuid=', false);
        $case['assessor']->forceFill(['status' => '0'])->save();
        $this->actingAs($case['assessor'])->getJson('/pkp/assessments?kind='.$kind.'&page=2')->assertForbidden();
    }

    public static function queueKinds(): array
    {
        return [['content'], ['document']];
    }

    public function test_bootstrap_assessment_http_contract(): void
    {
        $case = $this->createSubmittedCase();

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('data.state', 'approved')
            ->assertJsonPath('data.official_revision_uuid', $case['revision_uuid'])
            ->assertJsonPath('data.pending_revision_uuid', null)
            ->assertJsonPath('data.version', 4);
    }

    public function test_super_admin_with_positive_generic_gate_cannot_decide_an_unassigned_submission(): void
    {
        $case = $this->createSubmittedCase();
        $unassignedSuperAdmin = $this->createUserWithRole('super-admin');
        $payload = [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ];

        $this->assertTrue(Gate::forUser($unassignedSuperAdmin)->allows('pkp.contentDecision'));
        $this->actingAs($unassignedSuperAdmin)
            ->postJson('/pkp/'.$case['pkp_uuid'].'/decision', $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('pkp_decisions', 0);
        $this->assertDatabaseHas('pkp_submissions', ['uuid' => $case['submission_uuid'], 'state' => 'submitted']);
        $this->assertDatabaseHas('pkp', ['uuid' => $case['pkp_uuid'], 'version' => 3]);
    }

    public function test_assessor_queue_lists_only_current_assigned_content_attempts(): void
    {
        $case = $this->createSubmittedCase();

        $this->actingAs($case['assessor'])->getJson('/pkp/assessments?kind=content')
            ->assertOk()
            ->assertJsonPath('data.0.pkp_uuid', $case['pkp_uuid'])
            ->assertJsonPath('data.0.revision_uuid', $case['revision_uuid'])
            ->assertJsonPath('data.0.submission_uuid', $case['submission_uuid']);
    }

    public function test_return_requires_reason_and_preserves_the_submitted_attempt(): void
    {
        $case = $this->createSubmittedCase();
        $payload = [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'return',
        ];

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->assertDatabaseCount('pkp_decisions', 0);

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', array_merge($payload, [
            'reason' => 'Lengkapi satuan pada aktivitas kedua.',
        ]))->assertOk()
            ->assertJsonPath('data.state', 'returned')
            ->assertJsonPath('data.pending_revision_uuid', $case['revision_uuid'])
            ->assertJsonPath('data.official_revision_uuid', null);

        $this->assertDatabaseHas('pkp_submissions', [
            'uuid' => $case['submission_uuid'],
            'state' => 'returned',
        ]);
        $this->assertDatabaseHas('pkp_decisions', [
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'return',
            'reason' => 'Lengkapi satuan pada aktivitas kedua.',
        ]);
        $this->assertDatabaseCount('pkp_submissions', 1);
    }

    public function test_assessor_cannot_approve_before_the_assessed_month_ends(): void
    {
        $case = $this->createSubmittedCase();
        $this->travelTo(Carbon::create(2030, 2, 20, 12, 0, 0, 'Asia/Makassar'));

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertConflict()
            ->assertJsonPath('error.code', 'period_not_finished');

        $this->assertDatabaseCount('pkp_decisions', 0);
        $this->assertDatabaseHas('pkp_submissions', ['uuid' => $case['submission_uuid'], 'state' => 'submitted']);
        $this->assertDatabaseHas('pkp', ['uuid' => $case['pkp_uuid'], 'version' => 3, 'official_revision_uuid' => null]);
    }

    public function test_repeated_decision_cannot_replace_the_first_assessor_decision(): void
    {
        $case = $this->createSubmittedCase();
        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'return',
            'reason' => 'Please correct the form.',
        ])->assertOk();

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 4,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertConflict()
            ->assertJsonPath('error.code', 'submission_already_decided');

        $this->assertDatabaseCount('pkp_decisions', 1);
        $this->assertDatabaseHas('pkp_decisions', ['submission_uuid' => $case['submission_uuid'], 'decision' => 'return']);
    }

    /** @return array{owner: User, assessor: User, pkp_uuid: string, revision_uuid: string, submission_uuid: string} */
    private function createSubmittedCase(): array
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $owner = $this->createUserWithRole('user');
        $assessor = $this->createUserWithRole('user');
        $admin = $this->adminWithoutPegawai();
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->pegawai->uuid,
            [$assessor->pegawai->uuid]
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
        ])->assertOk();

        return [
            'owner' => $owner,
            'assessor' => $assessor,
            'pkp_uuid' => $pkpUuid,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submitted->json('data.submission_uuid'),
        ];
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
