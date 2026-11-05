<?php

namespace Tests\Integration\Pkp;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessmentService;
use App\Services\PkpAssessorSettingsService;
use App\Services\PkpDocumentService;
use App\Services\PkpSubmissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PkpConcurrencyHarness;
use Tests\TestCase;

class PkpWorkflowRaceTest extends TestCase
{
    private PkpConcurrencyHarness $harness;

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $storedDocuments = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new PkpConcurrencyHarness;
        $this->harness->assertRuntime($this);
        config(['cache.default' => 'array']);
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->storedDocuments as $path) {
            Storage::disk('local')->delete($path);
        }
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_maximum_tree_text_and_numbers_round_trip_exactly_on_the_deployment_engine(): void
    {
        $case = $this->createDraftCase();
        $activity = ['description' => str_repeat('D', 2000), 'unit' => str_repeat('U', 100),
            'target_credit' => '99999999.999999', 'target_quantity' => '9999999999.9999',
            'target_quality' => '100', 'realized_quantity' => '9999999999.9999'];
        $tree = array_fill(0, 50, ['title' => str_repeat('T', 500), 'activities' => array_fill(0, 10, $activity)]);
        app(PkpSubmissionService::class)->save($case['owner'], $case['pkp_uuid'], $case['revision_uuid'], 2, ['notes' => str_repeat('N', 5000), 'indicators' => $tree]);
        $result = app(PkpSubmissionService::class)->submit($case['owner'], $case['pkp_uuid'], $case['revision_uuid'], 3, $this->afterPeriod($case['year'], $case['month']));
        $snapshot = json_decode(DB::table('pkp_submissions')->where('uuid', $result->submissionUuid)->value('input_snapshot'), true);
        // MySQL JSON normalizes object keys; list order and exact string values remain contractual.
        $normalize = fn (array $rows) => array_map(fn (array $indicator) => [
            'title' => $indicator['title'],
            'activities' => array_map(function (array $activity): array {
                ksort($activity);

                return $activity;
            }, $indicator['activities']),
        ], $rows);
        $this->assertSame($normalize($tree), $normalize($snapshot['indicators']));
        $this->assertSame(str_repeat('N', 5000), $snapshot['notes']);
        $this->assertDatabaseCount('pkp_activities', 500);
        $this->assertDatabaseCount('pkp_indicators', 50);
    }

    public function test_identity_read_includes_employee_update_committed_before_the_history_statement(): void
    {
        $case = $this->createDraftCase();
        $results = $this->harness->runTogether([
            ['operation' => 'read_identity', 'actor_uuid' => $case['owner']->uuid,
                'year' => $case['year'], 'month' => $case['month'], 'identity_barrier' => 'before_history'],
            ['operation' => 'update_identity', 'actor_uuid' => $case['owner']->uuid],
        ]);
        $this->assertSame(200, $results[0]['status']);
        $this->assertSame(200, $results[1]['status']);
        $this->assertSame('Committed Updated Employee', $results[0]['identity']['employee']['name']);
        $this->assertSame('IV/b', $results[0]['identity']['rank']['value']);
        $this->assertSame('Committed Updated Unit', $results[0]['identity']['work_unit']['value']);
    }

    public function test_identity_update_committed_before_authoritative_read_rejects_approval(): void
    {
        $this->assertIdentityReadRace('before_read', 409);
    }

    public function test_identity_update_after_history_snapshot_does_not_mix_identity_versions(): void
    {
        $this->assertIdentityReadRace('after_history', 200);
    }

    private function assertIdentityReadRace(string $barrier, int $expectedStatus): void
    {
        $case = $this->createSubmittedCase();
        $results = $this->harness->runTogether([
            ['operation' => 'decision', 'actor_uuid' => $case['assessor']->uuid,
                'pkp_uuid' => $case['pkp_uuid'], 'revision_uuid' => $case['revision_uuid'],
                'submission_uuid' => $case['submission_uuid'], 'expected_version' => 3,
                'decision' => 'approve', 'identity_barrier' => $barrier],
            ['operation' => 'update_identity', 'actor_uuid' => $case['owner']->uuid],
        ]);
        $this->assertSame(200, $results[1]['status']);
        $this->assertSame($expectedStatus, $results[0]['status']);
        $this->assertSame($expectedStatus === 409 ? 'identity_changed' : null, $results[0]['code']);
        $this->assertDatabaseCount('pkp_decisions', $expectedStatus === 200 ? 1 : 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $case['pkp_uuid'], 'version' => $expectedStatus === 200 ? 4 : 3]);
        $snapshot = json_decode(DB::table('pkp_submissions')->where('uuid', $case['submission_uuid'])->value('identity_snapshot'), true);
        $this->assertSame('IV/a', $snapshot['rank']['value']);
        $this->assertNotSame('Committed Updated Unit', $snapshot['work_unit']['value']);
    }

    public function test_two_connections_use_the_selected_supported_engine(): void
    {
        $this->harness->assertIndependentCommittedConnections($this);
    }

    public function test_duplicate_month_creation_has_one_database_winner(): void
    {
        [$owner, $year, $month] = $this->createOwnerForPeriod();
        $results = $this->harness->runTogether([
            ['operation' => 'create', 'actor_uuid' => $owner->uuid, 'year' => $year, 'month' => $month, 'source' => 'application'],
            ['operation' => 'create', 'actor_uuid' => $owner->uuid, 'year' => $year, 'month' => $month, 'source' => 'application'],
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 409], $statuses);
        $this->assertDatabaseCount('pkp', 1);
        $this->assertDatabaseCount('pkp_revisions', 1);
    }

    public function test_concurrent_decision_and_reassignment_revoke_old_authority_atomically(): void
    {
        $case = $this->createSubmittedCase();
        $results = $this->harness->runTogether([
            [
                'operation' => 'reassign_content',
                'actor_uuid' => $case['admin']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'expected_version' => 3,
                'new_assessor_uuid' => $case['replacement']->pegawai->uuid,
                'reason' => 'Concurrent availability reassignment.',
            ],
            [
                'operation' => 'decision',
                'actor_uuid' => $case['assessor']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'submission_uuid' => $case['submission_uuid'],
                'expected_version' => 3,
                'decision' => 'approve',
            ],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['status'] === 200)));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => in_array($result['status'], [403, 409], true))));
        $pkp = DB::table('pkp')->where('uuid', $case['pkp_uuid'])->first();
        $revision = DB::table('pkp_revisions')->where('uuid', $case['revision_uuid'])->first();

        if ($pkp->official_revision_uuid === $case['revision_uuid']) {
            $this->assertSame(409, $results[0]['status']);
            $this->assertSame('revision_approved', $results[0]['code']);
            $this->assertSame($case['assessor']->pegawai->uuid, $revision->assessor_pegawai_uuid);
            $this->assertSame(1, DB::table('pkp_decisions')->count());
        } else {
            $this->assertSame(403, $results[1]['status']);
            $this->assertSame($case['replacement']->pegawai->uuid, $revision->assessor_pegawai_uuid);
            $this->assertSame(0, DB::table('pkp_decisions')->count());
        }
    }

    public function test_duplicate_correction_creation_has_one_pending_revision(): void
    {
        $case = $this->createSubmittedCase();
        app(\App\Services\PkpAssessmentService::class)->decide(
            $case['assessor'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            $case['submission_uuid'],
            3,
            'approve',
            null,
            $this->afterPeriod($case['year'], $case['month'])
        );
        $operations = array_fill(0, 2, [
            'operation' => 'revise',
            'actor_uuid' => $case['owner']->uuid,
            'pkp_uuid' => $case['pkp_uuid'],
            'base_revision_uuid' => $case['revision_uuid'],
            'expected_version' => 4,
            'source' => 'application',
        ]);

        $results = $this->harness->runTogether($operations);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseCount('pkp_revisions', 2);
        $this->assertDatabaseHas('pkp', [
            'uuid' => $case['pkp_uuid'],
            'version' => 5,
            'official_revision_uuid' => $case['revision_uuid'],
        ]);
        $this->assertNotNull(DB::table('pkp')->where('uuid', $case['pkp_uuid'])->value('pending_revision_uuid'));
    }

    public function test_duplicate_assessor_decisions_have_one_winner(): void
    {
        $case = $this->createSubmittedCase();
        $results = $this->harness->runTogether([
            [
                'operation' => 'decision',
                'actor_uuid' => $case['assessor']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'submission_uuid' => $case['submission_uuid'],
                'expected_version' => 3,
                'decision' => 'approve',
            ],
            [
                'operation' => 'decision',
                'actor_uuid' => $case['assessor']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'submission_uuid' => $case['submission_uuid'],
                'expected_version' => 3,
                'decision' => 'return',
                'reason' => 'Concurrent correction request.',
            ],
        ]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseCount('pkp_decisions', 1);
        $this->assertDatabaseCount('pkp_submissions', 1);
        $this->assertSame(4, (int) DB::table('pkp')->where('uuid', $case['pkp_uuid'])->value('version'));
    }

    public function test_document_acceptance_races_with_replacement_upload_without_losing_a(): void
    {
        $case = $this->createApprovedCaseWithFinalAndPendingReplacement();
        $replacementPath = $this->temporaryPdfPath('replacement C');
        $results = $this->harness->runTogether([
            [
                'operation' => 'decide_document',
                'actor_uuid' => $case['assessor']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'document_uuid' => $case['document_b_uuid'],
                'expected_version' => 7,
            ],
            [
                'operation' => 'upload_final',
                'actor_uuid' => $case['owner']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'expected_version' => 7,
                'file_path' => $replacementPath,
            ],
        ]);

        $this->assertSame(200, $results[0]['status']);
        $this->assertSame(409, $results[1]['status']);
        $this->assertContains($results[1]['code'], ['pending_document_exists', 'stale_version']);
        $revision = DB::table('pkp_revisions')->where('uuid', $case['revision_uuid'])->first();
        $this->assertSame($case['document_b_uuid'], $revision->final_document_uuid);
        $this->assertNull($revision->pending_final_document_uuid);
        $this->assertSame(2, DB::table('pkp_documents')->where('revision_uuid', $case['revision_uuid'])->count());
    }

    public function test_inspector_reassignment_races_with_old_inspector_decision(): void
    {
        $case = $this->createApprovedCaseWithFinalAndPendingReplacement();
        $results = $this->harness->runTogether([
            [
                'operation' => 'reassign_inspector',
                'actor_uuid' => $case['admin']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'expected_version' => 7,
                'new_inspector_uuid' => $case['replacement']->pegawai->uuid,
                'reason' => 'The current inspector is unavailable during review.',
            ],
            [
                'operation' => 'decide_document',
                'actor_uuid' => $case['assessor']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'document_uuid' => $case['document_b_uuid'],
                'expected_version' => 7,
            ],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['status'] === 200)));
        $revision = DB::table('pkp_revisions')->where('uuid', $case['revision_uuid'])->first();
        if ($revision->inspector_pegawai_uuid === $case['replacement']->pegawai->uuid) {
            $this->assertSame(403, $results[1]['status']);
            $this->assertSame($case['document_a_uuid'], $revision->final_document_uuid);
            $this->assertSame($case['document_b_uuid'], $revision->pending_final_document_uuid);
            $this->assertCount(1, app(PkpAssessmentService::class)->queue($case['replacement'], 'document'));
        } else {
            $this->assertSame(409, $results[0]['status']);
            $this->assertSame($case['document_b_uuid'], $revision->final_document_uuid);
            $this->assertNull($revision->pending_final_document_uuid);
            $this->assertSame($case['assessor']->pegawai->uuid, $revision->inspector_pegawai_uuid);
        }
    }

    public function test_deadline_and_first_submission_commit_one_coherent_snapshot(): void
    {
        $case = $this->createDraftCase();
        $deadlineLocal = CarbonImmutable::create($case['year'], $case['month'], 1, 0, 0, 0, 'Asia/Makassar')
            ->addMonthNoOverflow()
            ->subSecond()
            ->format('Y-m-d\TH:i:s');
        $results = $this->harness->runTogether([
            [
                'operation' => 'configure_deadline',
                'actor_uuid' => $case['admin']->uuid,
                'year' => $case['year'],
                'month' => $case['month'],
                'expected_version' => 0,
                'deadline_local' => $deadlineLocal,
            ],
            [
                'operation' => 'submit',
                'actor_uuid' => $case['owner']->uuid,
                'pkp_uuid' => $case['pkp_uuid'],
                'revision_uuid' => $case['revision_uuid'],
                'expected_version' => 2,
            ],
        ]);

        $this->assertSame([200, 200], array_column($results, 'status'));
        $pkp = \Illuminate\Support\Facades\DB::table('pkp')->where('uuid', $case['pkp_uuid'])->first();
        $deadline = \Illuminate\Support\Facades\DB::table('pkp_deadlines')
            ->where('year', $case['year'])
            ->where('month', $case['month'])
            ->first();
        $this->assertContains($pkp->lateness, ['late', 'deadline_not_set']);
        if ($pkp->lateness === 'late') {
            $this->assertNotNull($pkp->deadline_snapshot_at);
            $this->assertSame($deadline->deadline_at, $pkp->deadline_snapshot_at);
        } else {
            $this->assertNull($pkp->deadline_snapshot_at);
            $this->assertNotNull($deadline->deadline_at);
            $submissionEventAt = DB::table('pkp_events')
                ->where('pkp_uuid', $case['pkp_uuid'])
                ->where('event_type', 'submitted')
                ->value('occurred_at');
            $deadlineEventAt = DB::table('pkp_configuration_events')
                ->where('kind', 'deadline')
                ->orderByDesc('created_at')
                ->value('created_at');
            $this->assertGreaterThanOrEqual($submissionEventAt, $deadlineEventAt);
        }
    }

    /** @return array{User, int, int} */
    private function createOwnerForPeriod(): array
    {
        [$owner, , , , $year, $month] = $this->createActorsAndPeriod();

        return [$owner, $year, $month];
    }

    /** @return array{owner: User, assessor: User, replacement: User, admin: User, year: int, month: int, pkp_uuid: string, revision_uuid: string, submission_uuid: string} */
    private function createSubmittedCase(): array
    {
        $case = $this->createDraftCase();
        $submission = app(PkpSubmissionService::class)->submit(
            $case['owner'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            2,
            $this->afterPeriod($case['year'], $case['month'])
        );

        return array_merge($case, ['submission_uuid' => $submission->submissionUuid]);
    }

    /** @return array{owner: User, assessor: User, replacement: User, admin: User, year: int, month: int, pkp_uuid: string, revision_uuid: string} */
    private function createDraftCase(): array
    {
        [$owner, $assessor, $replacement, $admin, $year, $month] = $this->createActorsAndPeriod();
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->pegawai->uuid,
            [$assessor->pegawai->uuid, $replacement->pegawai->uuid]
        );
        $this->createIdentityHistory($owner->pegawai);
        $created = app(PkpSubmissionService::class)->create($owner, $year, $month, 'application');
        $this->assertSame(1, $created->version);
        app(PkpSubmissionService::class)->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, [
            'indicators' => [[
                'title' => 'Kinerja',
                'activities' => [[
                    'description' => 'Complete assessment',
                    'unit' => 'document',
                    'target_credit' => '1.000000',
                    'target_quantity' => '1.0000',
                    'target_quality' => '100.0000',
                    'realized_quantity' => '1.0000',
                ]],
            ]],
        ]);

        return [
            'owner' => $owner,
            'assessor' => $assessor,
            'replacement' => $replacement,
            'admin' => $admin,
            'year' => $year,
            'month' => $month,
            'pkp_uuid' => $created->recordUuid,
            'revision_uuid' => $created->pendingRevisionUuid,
        ];
    }

    /** @return array{owner: User, assessor: User, replacement: User, admin: User, year: int, month: int, pkp_uuid: string, revision_uuid: string, submission_uuid: string, document_a_uuid: string, document_b_uuid: string} */
    private function createApprovedCaseWithFinalAndPendingReplacement(): array
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        $case = $this->createSubmittedCase();
        app(PkpAssessmentService::class)->decide(
            $case['assessor'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            $case['submission_uuid'],
            3,
            'approve',
            null,
            $this->afterPeriod($case['year'], $case['month'])
        );
        $documents = app(PkpDocumentService::class);
        $documentA = $documents->store(
            $case['owner'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            4,
            'signed_final',
            UploadedFile::fake()->createWithContent('race-final-a.pdf', $this->pdfBytes('accepted A'))
        );
        $documents->decide(
            $case['assessor'],
            $case['pkp_uuid'],
            $documentA->documentUuid,
            $case['revision_uuid'],
            5,
            'accept',
            null,
            true
        );
        $documentB = $documents->store(
            $case['owner'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            6,
            'signed_final',
            UploadedFile::fake()->createWithContent('race-final-b.pdf', $this->pdfBytes('pending B')),
            false,
            true
        );
        $case['document_a_uuid'] = $documentA->documentUuid;
        $case['document_b_uuid'] = $documentB->documentUuid;
        foreach ([$documentA->documentUuid, $documentB->documentUuid] as $documentUuid) {
            $this->storedDocuments[] = DB::table('pkp_documents')->where('uuid', $documentUuid)->value('storage_path');
        }

        return $case;
    }

    private function temporaryPdfPath(string $label): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkp-race-upload-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create a concurrent upload fixture.');
        }
        file_put_contents($path, $this->pdfBytes($label));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function pdfBytes(string $label): string
    {
        return Pdf::loadHTML('<html><body><h1>'.e($label).'</h1></body></html>')->output();
    }

    /** @return array{User, User, User, User, int, int} */
    private function createActorsAndPeriod(): array
    {
        $owner = $this->createUserWithRole('user');
        $assessor = $this->createUserWithRole('user');
        $replacement = $this->createUserWithRole('user');
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'pkp.manage-assessors', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'pkp.reassign', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'pkp.manage-deadlines', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        $period = CarbonImmutable::now('Asia/Makassar')->subMonths(2)->startOfMonth();

        return [$owner, $assessor, $replacement, $admin, $period->year, $period->month];
    }

    private function createIdentityHistory(Pegawai $pegawai): void
    {
        $jabatan = Jabatan::factory()->create(['nama' => 'Race Test Judge']);
        RiwayatPangkat::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'pangkat_golongan' => 'IV/a',
            'tmt' => '2020-01-01',
            'nomor_sk' => 'RACE-RANK-1',
            'tanggal_sk' => '2020-01-01',
        ]);
        RiwayatJabatan::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jabatan_uuid' => $jabatan->uuid,
            'satuan_kerja' => 'PN Concurrency Test',
            'tanggal_mulai' => '2020-01-01',
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        );
        $user = User::factory()->create([
            'status' => '1',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        Pegawai::factory()->create([
            'user_uuid' => $user->uuid,
            'nip' => $user->nip,
        ]);

        return $user;
    }

    private function afterPeriod(int $year, int $month): \DateTimeImmutable
    {
        return CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Makassar')
            ->addMonthNoOverflow()
            ->addHour()
            ->toDateTimeImmutable();
    }
}
