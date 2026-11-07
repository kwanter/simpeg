<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use App\Services\PkpSubmissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SimpegTestCase;

class PkpRevisionTest extends SimpegTestCase
{
    public function test_official_inspector_keeps_r1_access_while_r2_is_private_and_pending(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $inspector] = $this->createOwnerAndAssessor();
        [$pkpUuid, $r1, $s1] = $this->submitApplicationWithNotes($owner, 'Frozen R1 notes');
        $this->actingAs($inspector)->postJson('/pkp/'.$pkpUuid.'/decision', ['expected_version' => 3, 'revision_uuid' => $r1, 'submission_uuid' => $s1, 'decision' => 'approve'])->assertOk();
        $candidate = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkpUuid.'/revisions/'.$r1.'/documents', [
            'expected_version' => 4, 'kind' => 'signed_final',
            'file' => UploadedFile::fake()->createWithContent('pending-r1.pdf', Pdf::loadHTML('<p>R1 evidence</p>')->output()),
        ])->assertCreated();
        $newAssessor = $this->createUserWithRole('user');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('pkp.manage-assessors');
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 1, $newAssessor->pegawai->uuid, [$inspector->pegawai->uuid, $newAssessor->pegawai->uuid]);
        $correction = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/revisions', ['expected_version' => 5, 'base_revision_uuid' => $r1, 'source' => 'application'])->assertCreated();
        $r2 = $correction->json('data.revision_uuid');
        $tree = $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid.'/revisions/'.$r2.'/edit')->assertOk()->json('data.indicators');
        $tree[0]['activities'][0]['realized_quantity'] = '3';
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$r2, ['expected_version' => 6, 'notes' => 'PRIVATE R2 notes', 'indicators' => $tree])->assertOk();

        $this->actingAs($inspector)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonPath('data.revision_uuid', $r1)->assertJsonPath('data.pending', null)
            ->assertJsonPath('data.capabilities.inspect_document', true)->assertDontSee('PRIVATE R2 notes');
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonPath('data.official.notes', 'Frozen R1 notes')->assertJsonPath('data.pending.notes', 'PRIVATE R2 notes')
            ->assertJsonPath('data.official.completeness', 'awaiting_inspection')->assertJsonPath('data.pending.completeness', 'incomplete')
            ->assertJsonPath('data.official.score.overall_score.numerator', '200')->assertJsonPath('data.pending.score.overall_score.numerator', '100');
        $this->actingAs($owner)->withHeader('Accept', 'text/html')->get('/pkp/'.$pkpUuid)->assertOk()->assertSee('PKP resmi')->assertSee('Revisi pending');
        $this->actingAs($inspector)->withHeader('Accept', 'text/html')->get('/pkp/assessments?kind=document')->assertOk()->assertSee('revision_uuid='.$r1, false);
        $this->actingAs($inspector)->getJson('/pkp/'.$pkpUuid.'?revision_uuid='.$r2)->assertForbidden();
        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', ['expected_version' => 7, 'revision_uuid' => $r2])->assertOk();
        $this->actingAs($inspector)->getJson('/pkp/'.$pkpUuid.'?revision_uuid='.$r1)->assertOk()->assertDontSee('PRIVATE R2 notes');
        $this->actingAs($newAssessor)->getJson('/pkp/'.$pkpUuid)->assertOk()->assertJsonPath('data.revision_uuid', $r2)->assertJsonPath('data.official', null)->assertDontSee('Frozen R1 notes');
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid.'?revision_uuid='.(string) Str::uuid())->assertNotFound();
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid.'?revision_uuid=invalid')->assertUnprocessable();
        $this->actingAs($inspector)->postJson('/pkp/'.$pkpUuid.'/documents/'.$candidate->json('data.document_uuid').'/decision', [
            'expected_version' => 8, 'revision_uuid' => $r1, 'decision' => 'accept', 'matches_approved_content' => true,
        ])->assertOk();
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'official_revision_uuid' => $r1, 'pending_revision_uuid' => $r2]);
        $this->assertDatabaseHas('pkp_submissions', ['uuid' => $submitted->json('data.submission_uuid'), 'state' => 'submitted']);
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()->assertJsonPath('data.official.completeness', 'complete')
            ->assertJsonPath('data.pending.completeness', 'awaiting_content_approval');
    }

    public function test_small_credit_submission_retains_decimal_input_and_exact_rational_credit(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessor();
        [, , $submissionUuid] = $this->submitApplicationWithNotes($owner, 'Small credit', '0.000001');
        $submission = DB::table('pkp_submissions')->where('uuid', $submissionUuid)->first();
        $this->assertSame('0.000001', json_decode($submission->input_snapshot, true)['indicators'][0]['activities'][0]['target_credit']);
        $credit = json_decode($submission->score_snapshot, true)['indicators'][0]['activities'][0]['realized_credit'];
        $this->assertSame('1', $credit['numerator']);
        $this->assertSame('500000', $credit['denominator']);
    }

    public function test_owner_correction_and_identity_refresh_capabilities_match_the_form_and_state(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        [$pkpUuid, $revisionUuid, $submissionUuid] = $this->submitApplicationWithNotes($owner, 'Frozen notes');
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonPath('data.capabilities.revise', false)->assertJsonPath('data.capabilities.refresh_identity', true);
        $this->actingAs($assessor)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonPath('data.capabilities.revise', false)->assertJsonPath('data.capabilities.refresh_identity', false);
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3, 'revision_uuid' => $revisionUuid, 'submission_uuid' => $submissionUuid, 'decision' => 'approve',
        ])->assertOk();
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonPath('data.capabilities.revise', true)->assertJsonPath('data.capabilities.refresh_identity', false);
        $this->actingAs($owner)->get('/pkp/'.$pkpUuid)->assertOk()->assertSee('Buat revisi koreksi')
            ->assertSee('name="base_revision_uuid" value="'.$revisionUuid.'"', false);
        $this->actingAs($assessor)->get('/pkp/'.$pkpUuid)->assertOk()->assertDontSee('Buat revisi koreksi');
        $correction = $this->actingAs($owner)->post('/pkp/'.$pkpUuid.'/revisions', [
            'expected_version' => 4, 'base_revision_uuid' => $revisionUuid, 'source' => 'application',
        ])->assertStatus(303);
        $pending = DB::table('pkp')->where('uuid', $pkpUuid)->value('pending_revision_uuid');
        $correction->assertRedirect('/pkp/'.$pkpUuid.'/revisions/'.$pending.'/edit');
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()->assertJsonPath('data.capabilities.revise', false);
        $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/revisions', [
            'expected_version' => 5, 'base_revision_uuid' => $revisionUuid, 'source' => 'application',
        ])->assertConflict()->assertJsonPath('error.code', 'pending_revision_exists');
        $other = $this->createUserWithRole('user');
        $this->actingAs($other)->postJson('/pkp/'.$pkpUuid.'/revisions', [
            'expected_version' => 5, 'base_revision_uuid' => $revisionUuid, 'source' => 'application',
        ])->assertForbidden();
        $this->assertDatabaseCount('pkp_revisions', 2);
    }

    public function test_returned_notes_edits_and_resubmission_preserve_the_original_attempt_snapshot(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        [$pkpUuid, $revisionUuid, $submissionUuid] = $this->submitApplicationWithNotes($owner, 'First frozen notes');
        $original = DB::table('pkp_submissions')->where('uuid', $submissionUuid)->first();
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3, 'revision_uuid' => $revisionUuid, 'submission_uuid' => $submissionUuid,
            'decision' => 'return', 'reason' => 'Clarify the notes.',
        ])->assertOk();
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 4, 'notes' => 'Corrected notes', 'assessor_pegawai_uuid' => $assessor->pegawai->uuid,
        ])->assertOk();
        $resubmitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', ['expected_version' => 5, 'revision_uuid' => $revisionUuid])->assertOk();
        $this->assertNotSame($submissionUuid, $resubmitted->json('data.submission_uuid'));
        $this->assertDatabaseHas('pkp_submissions', ['uuid' => $submissionUuid, 'state' => 'returned', 'input_snapshot' => $original->input_snapshot, 'payload_sha256' => $original->payload_sha256]);
        $new = DB::table('pkp_submissions')->where('uuid', $resubmitted->json('data.submission_uuid'))->first();
        $this->assertSame('Corrected notes', json_decode($new->input_snapshot, true)['notes']);
    }

    private function submitApplicationWithNotes(User $owner, string $notes, string $credit = '1.000000'): array
    {
        $created = $this->actingAs($owner)->postJson('/pkp', ['year' => 2030, 'month' => 2, 'source' => 'application'])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1, 'notes' => $notes,
            'indicators' => [['title' => 'Kinerja', 'activities' => [[
                'description' => 'Menyelesaikan target kerja', 'unit' => 'dokumen', 'target_credit' => $credit,
                'target_quantity' => '3.0000', 'target_quality' => '100.0000', 'realized_quantity' => '2.0000',
            ]]]],
        ])->assertOk();
        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', ['expected_version' => 2, 'revision_uuid' => $revisionUuid])->assertOk();

        return [$pkpUuid, $revisionUuid, $submitted->json('data.submission_uuid')];
    }

    public function test_bootstrap_content_correction_http_contract(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $tree = [[
            'title' => 'Kinerja',
            'activities' => [[
                'description' => 'Menyelesaikan target kerja',
                'unit' => 'dokumen',
                'target_credit' => '1.000000',
                'target_quantity' => '3.0000',
                'target_quality' => '100.0000',
                'realized_quantity' => '2.0000',
            ]],
        ]];
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => $tree,
        ])->assertOk();
        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk();
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submitted->json('data.submission_uuid'),
            'decision' => 'approve',
        ])->assertOk();
        $pdf = Pdf::loadHTML('<html><body><h1>Approved signed PKP</h1></body></html>')->output();
        $uploaded = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('signed-final.pdf', $pdf),
            ]
        )->assertCreated();
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/documents/'.$uploaded->json('data.document_uuid').'/decision', [
            'expected_version' => 5,
            'revision_uuid' => $revisionUuid,
            'decision' => 'accept',
            'matches_approved_content' => true,
        ])->assertOk();

        $correction = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/revisions', [
            'expected_version' => 6,
            'base_revision_uuid' => $revisionUuid,
            'source' => 'application',
        ])->assertCreated()
            ->assertJsonPath('data.revision_number', 2)
            ->assertJsonPath('data.version', 7);

        $correctionUuid = $correction->json('data.revision_uuid');
        $this->assertDatabaseHas('pkp', [
            'uuid' => $pkpUuid,
            'official_revision_uuid' => $revisionUuid,
            'pending_revision_uuid' => $correctionUuid,
            'version' => 7,
        ]);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'state' => 'approved',
            'final_document_uuid' => $uploaded->json('data.document_uuid'),
        ]);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $correctionUuid,
            'number' => 2,
            'source' => 'application',
            'state' => 'draft',
            'approved_submission_uuid' => null,
            'source_document_uuid' => null,
            'final_document_uuid' => null,
            'pending_final_document_uuid' => null,
        ]);
        $this->assertSame(1, DB::table('pkp_indicators')->where('revision_uuid', $correctionUuid)->count());
        $this->assertSame(1, DB::table('pkp_activities')
            ->join('pkp_indicators', 'pkp_indicators.uuid', '=', 'pkp_activities.indicator_uuid')
            ->where('pkp_indicators.revision_uuid', $correctionUuid)
            ->count());
        $this->assertDatabaseMissing('pkp_documents', ['revision_uuid' => $correctionUuid]);
        $this->assertDatabaseMissing('pkp_submissions', ['revision_uuid' => $correctionUuid]);
        $secondAttempt = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', ['expected_version' => 7, 'revision_uuid' => $correctionUuid])->assertOk()->json('data.submission_uuid');
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', ['expected_version' => 8, 'revision_uuid' => $correctionUuid, 'submission_uuid' => $secondAttempt, 'decision' => 'approve'])->assertOk();
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonPath('data.official.revision_uuid', $correctionUuid)->assertJsonPath('data.official.completeness', 'awaiting_signed_upload');
        $this->actingAs($owner)->getJson('/pkp?year=2030')->assertOk()->assertJsonPath('data.0.document_complete', false);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $revisionUuid, 'final_document_uuid' => $uploaded->json('data.document_uuid')]);

    }

    public function test_changed_identity_withdraws_attempt_and_preserves_first_submission_classification(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $tree = [[
            'title' => 'Kinerja',
            'activities' => [[
                'description' => 'Menyelesaikan target kerja',
                'unit' => 'dokumen',
                'target_credit' => '1.000000',
                'target_quantity' => '3.0000',
                'target_quality' => '100.0000',
                'realized_quantity' => '2.0000',
            ]],
        ]];
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => $tree,
        ])->assertOk();
        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk();
        $submissionUuid = $submitted->json('data.submission_uuid');
        $original = DB::table('pkp')->where('uuid', $pkpUuid)->first();
        $originalSubmission = DB::table('pkp_submissions')->where('uuid', $submissionUuid)->first();

        RiwayatPangkat::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $owner->pegawai->uuid,
            'pangkat_golongan' => 'V/a',
            'tmt' => '2030-02-20',
            'nomor_sk' => 'SK-RANK-2',
            'tanggal_sk' => '2030-02-20',
        ]);

        $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/refresh-identity', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'reason' => 'The effective rank changed during the review period.',
        ])->assertOk()
            ->assertJsonPath('data.state', 'returned')
            ->assertJsonPath('data.version', 4);

        $this->assertDatabaseHas('pkp_submissions', [
            'uuid' => $submissionUuid,
            'state' => 'withdrawn_identity_changed',
            'identity_sha256' => $originalSubmission->identity_sha256,
        ]);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'state' => 'returned',
            'current_submission_uuid' => null,
        ]);
        $this->assertDatabaseHas('pkp', [
            'uuid' => $pkpUuid,
            'version' => 4,
            'first_submitted_at' => $original->first_submitted_at,
            'deadline_snapshot_at' => null,
            'lateness' => 'deadline_not_set',
        ]);
    }

    public function test_owner_and_unassigned_administrators_cannot_decide_or_access_foreign_evidence(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        [$pkp, $revision, $submission] = $this->submitApplicationWithNotes($owner, 'Immutable authorization evidence');
        $admin = $this->createUserWithRole('admin');
        $superAdmin = $this->createUserWithRole('super-admin');
        foreach (['pkp.manage-assessors', 'pkp.manage-deadlines', 'pkp.reassign'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
            $admin->givePermissionTo($permission);
            $superAdmin->givePermissionTo($permission);
        }
        $payload = ['expected_version' => 3, 'revision_uuid' => $revision, 'submission_uuid' => $submission, 'decision' => 'approve'];
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($superAdmin)->allows('pkp.contentDecision'));
        foreach ([$owner, $admin, $superAdmin] as $actor) {
            $this->actingAs($actor)->postJson('/pkp/'.$pkp.'/decision', $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('pkp_decisions', 0);
        $score = DB::table('pkp_submissions')->where('uuid', $submission)->value('score_snapshot');
        $this->actingAs($assessor)->postJson('/pkp/'.$pkp.'/decision', $payload + ['score' => '100'])->assertUnprocessable();
        $this->assertSame($score, DB::table('pkp_submissions')->where('uuid', $submission)->value('score_snapshot'));
        $this->actingAs($assessor)->postJson('/pkp/'.$pkp.'/decision', $payload)->assertOk();
        $uploaded = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkp.'/revisions/'.$revision.'/documents', [
            'expected_version' => 4, 'kind' => 'signed_final',
            'file' => UploadedFile::fake()->createWithContent('authorization.pdf', Pdf::loadHTML('<h1>Authorization fixture</h1>')->output()),
        ])->assertCreated();
        $document = $uploaded->json('data.document_uuid');
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($superAdmin)->allows('pkp.documentDecision'));
        foreach ([$owner, $admin, $superAdmin] as $actor) {
            $this->actingAs($actor)->postJson('/pkp/'.$pkp.'/documents/'.$document.'/decision', ['expected_version' => 5, 'revision_uuid' => $revision, 'decision' => 'accept', 'matches_approved_content' => true])->assertForbidden();
        }
        $this->assertDatabaseCount('pkp_document_reviews', 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkp, 'version' => 5]);
        $stranger = $this->createUserWithRole('user');
        $this->actingAs($stranger)->getJson('/pkp/'.$pkp)->assertForbidden();
        $this->actingAs($stranger)->getJson('/pkp/'.$pkp.'/revisions/'.$revision.'/edit')->assertForbidden();
        $this->actingAs($owner)->getJson('/pkp/'.$pkp.'/revisions/'.$revision.'/edit')->assertConflict()->assertJsonPath('error.code', 'revision_not_editable');
        $this->actingAs($stranger)->get('/pkp/'.$pkp.'/documents/'.$document.'/download')->assertForbidden();
        $other = app(PkpSubmissionService::class)->create($owner, 2030, 1, 'application');
        $this->actingAs($owner)->get('/pkp/'.$other->recordUuid.'/revisions/'.$revision.'/pdf')->assertNotFound();
        $this->actingAs($owner)->get('/pkp/'.$other->recordUuid.'/documents/'.$document.'/download')->assertNotFound();
        $this->actingAs($assessor)->postJson('/pkp/'.$other->recordUuid.'/decision', $payload)->assertNotFound();
        $this->assertSame($score, DB::table('pkp_submissions')->where('uuid', $submission)->value('score_snapshot'));
    }

    public function test_incomplete_drafts_never_capture_first_submission_until_their_fields_are_completed(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessor();
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 2, 'application');
        foreach ([[], [['title' => 'Empty indicator', 'activities' => []]], [['title' => null, 'activities' => [['description' => null, 'unit' => null, 'target_credit' => null, 'target_quantity' => null, 'target_quality' => null, 'realized_quantity' => null]]]]] as $index => $tree) {
            $saved = $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, $index + 1, ['indicators' => $tree]);
            $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => $saved->version, 'revision_uuid' => $created->pendingRevisionUuid])->assertUnprocessable()->assertJsonValidationErrors('indicators');
            $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => $saved->version, 'first_submitted_at' => null, 'lateness' => null]);
            $this->assertDatabaseCount('pkp_submissions', 0);
            $this->actingAs($owner)->getJson('/pkp/'.$created->recordUuid)->assertOk()->assertJsonPath('data.score', null);
        }
        $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 4, ['indicators' => [[
            'title' => 'Complete', 'activities' => [['description' => 'Now ready', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
        ]]]);
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => 5, 'revision_uuid' => $created->pendingRevisionUuid])->assertOk();
        $this->assertDatabaseCount('pkp_submissions', 1);
    }

    public function test_missing_source_storage_prevents_first_submission_and_allows_recovery(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessor();
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'external');
        $bytes = Pdf::loadHTML('<h1>Source storage recovery</h1>')->output();
        $uploaded = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid.'/documents', [
            'expected_version' => 1, 'kind' => 'source', 'declared_signed' => '0', 'file' => UploadedFile::fake()->createWithContent('source.pdf', $bytes),
        ])->assertCreated();
        $path = DB::table('pkp_documents')->where('uuid', $uploaded->json('data.document_uuid'))->value('storage_path');
        Storage::disk('local')->delete($path);
        $payload = ['expected_version' => 2, 'revision_uuid' => $created->pendingRevisionUuid];
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', $payload)->assertServiceUnavailable()->assertJsonPath('error.code', 'document_storage_failed');
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 2, 'first_submitted_at' => null, 'lateness' => null]);
        $this->assertDatabaseCount('pkp_submissions', 0);
        Storage::disk('local')->put($path, $bytes);
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', $payload)->assertOk();
        $this->assertDatabaseCount('pkp_submissions', 1);
    }

    public function test_all_mutation_routes_reject_nonpositive_versions_without_side_effects(): void
    {
        [$owner] = $this->createOwnerAndAssessor();
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'application');
        foreach ([0, -1] as $version) {
            $this->actingAs($owner)->putJson('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid, ['expected_version' => $version])->assertUnprocessable()->assertJsonValidationErrors('expected_version');
            foreach ([
                'submit' => ['revision_uuid' => $created->pendingRevisionUuid],
                'refresh-identity' => ['revision_uuid' => $created->pendingRevisionUuid, 'reason' => 'Invalid version.'],
                'revisions' => ['base_revision_uuid' => $created->pendingRevisionUuid, 'source' => 'application'],
                'decision' => ['revision_uuid' => $created->pendingRevisionUuid, 'submission_uuid' => (string) Str::uuid(), 'decision' => 'approve'],
                'documents/'.Str::uuid().'/decision' => ['revision_uuid' => $created->pendingRevisionUuid, 'decision' => 'accept', 'matches_approved_content' => true],
            ] as $action => $data) {
                $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/'.$action, ['expected_version' => $version] + $data)->assertUnprocessable()->assertJsonValidationErrors('expected_version');
            }
        }
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 1, 'first_submitted_at' => null]);
        $this->assertDatabaseCount('pkp_submissions', 0);
    }

    /** @dataProvider exactApprovalBoundaries */
    public function test_exact_month_end_approval_boundary_applies_to_both_sources(string $source, string $approvalTime, int $status): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2030-01-31T23:59:58+08:00'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 1, $source);
        if ($source === 'application') {
            $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, ['indicators' => [[
                'title' => 'Boundary evidence', 'activities' => [['description' => 'Month end', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
            ]]]);
        } else {
            $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid.'/documents', [
                'expected_version' => 1, 'kind' => 'source', 'declared_signed' => '0', 'file' => UploadedFile::fake()->createWithContent('boundary.pdf', Pdf::loadHTML('<h1>Month end</h1>')->output()),
            ])->assertCreated();
        }
        $submitted = $service->submit($owner, $created->recordUuid, $created->pendingRevisionUuid, 2, now()->toDateTimeImmutable());
        $this->travelTo(Carbon::parse($approvalTime));
        $response = $this->actingAs($assessor)->postJson('/pkp/'.$created->recordUuid.'/decision', [
            'expected_version' => 3, 'revision_uuid' => $created->pendingRevisionUuid, 'submission_uuid' => $submitted->submissionUuid, 'decision' => 'approve',
        ])->assertStatus($status);
        if ($status === 409) {
            $response->assertJsonPath('error.code', 'period_not_finished');
            $this->assertDatabaseCount('pkp_decisions', 0);
        } else {
            $response->assertJsonPath('data.state', 'approved');
            $this->assertDatabaseCount('pkp_decisions', 1);
        }
    }

    public static function exactApprovalBoundaries(): array
    {
        return [
            ['application', '2030-01-31T23:59:59+08:00', 409],
            ['application', '2030-02-01T00:00:00+08:00', 200],
            ['external', '2030-01-31T23:59:59+08:00', 409],
            ['external', '2030-02-01T00:00:00+08:00', 200],
        ];
    }

    public function test_changed_settings_preserve_submitted_assignment_and_reject_revoked_draft_selection(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $oldAssessor] = $this->createOwnerAndAssessor();
        $service = app(PkpSubmissionService::class);
        $draft = $service->create($owner, 2030, 1, 'application');
        $service->save($owner, $draft->recordUuid, $draft->pendingRevisionUuid, 1, ['indicators' => [[
            'title' => 'Revoked selection', 'activities' => [['description' => 'Still editable', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
        ]]]);
        [$pkp, $revision, $submission] = $this->submitApplicationWithNotes($owner, 'Frozen old assignment');
        $replacement = $this->createUserWithRole('user');
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'pkp.manage-assessors', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 1, $replacement->pegawai->uuid, [$replacement->pegawai->uuid]);
        $this->actingAs($owner)->getJson('/pkp/my-assessor-options')->assertOk()
            ->assertJsonPath('data.default_assessor_uuid', $replacement->pegawai->uuid)->assertDontSee($oldAssessor->pegawai->uuid);
        $this->actingAs($owner)->postJson('/pkp/'.$draft->recordUuid.'/submit', ['expected_version' => 2, 'revision_uuid' => $draft->pendingRevisionUuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $this->assertDatabaseHas('pkp', ['uuid' => $draft->recordUuid, 'version' => 2, 'first_submitted_at' => null]);
        $this->actingAs($oldAssessor)->postJson('/pkp/'.$pkp.'/decision', ['expected_version' => 3, 'revision_uuid' => $revision, 'submission_uuid' => $submission, 'decision' => 'approve'])->assertOk();
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $revision, 'assessor_pegawai_uuid' => $oldAssessor->pegawai->uuid]);
        $withoutSettings = $this->createUserWithRole('user');
        $unset = $service->create($withoutSettings, 2030, 1, 'application');
        $this->actingAs($withoutSettings)->postJson('/pkp/'.$unset->recordUuid.'/submit', ['expected_version' => 1, 'revision_uuid' => $unset->pendingRevisionUuid])
            ->assertUnprocessable()->assertJsonValidationErrors('assessor_pegawai_uuid');
        $this->assertDatabaseHas('pkp', ['uuid' => $unset->recordUuid, 'version' => 1, 'first_submitted_at' => null]);
    }

    /** @dataProvider correctionModeSources */
    public function test_source_mode_correction_starts_empty_and_preserves_base_evidence(string $source, ?string $requestedSource = null): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 2, $source);
        if ($source === 'application') {
            $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, ['notes' => 'Original base notes', 'indicators' => [[
                'title' => 'Base activity', 'activities' => [['description' => 'Preserve approved content', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
            ]]]);
        } else {
            $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid.'/documents', [
                'expected_version' => 1, 'kind' => 'source', 'declared_signed' => '1', 'file' => UploadedFile::fake()->createWithContent('mode-source.pdf', Pdf::loadHTML('<h1>Preserve external base</h1>')->output()),
            ])->assertCreated();
        }
        $submitted = $service->submit($owner, $created->recordUuid, $created->pendingRevisionUuid, 2, now()->toDateTimeImmutable());
        $this->actingAs($assessor)->postJson('/pkp/'.$created->recordUuid.'/decision', ['expected_version' => 3, 'revision_uuid' => $created->pendingRevisionUuid, 'submission_uuid' => $submitted->submissionUuid, 'decision' => 'approve'])->assertOk();
        $before = DB::table('pkp')->where('uuid', $created->recordUuid)->first();
        $base = DB::table('pkp_revisions')->where('uuid', $created->pendingRevisionUuid)->first();
        $nextSource = $requestedSource ?? ($source === 'application' ? 'external' : 'application');
        $correction = $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/revisions', ['expected_version' => 4, 'base_revision_uuid' => $created->pendingRevisionUuid, 'source' => $nextSource])->assertCreated();
        $next = $correction->json('data.revision_uuid');
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $next, 'source' => $nextSource, 'notes' => null, 'source_document_uuid' => null, 'final_document_uuid' => null]);
        $this->assertSame(0, DB::table('pkp_indicators')->where('revision_uuid', $next)->count());
        $this->assertSame($base->approved_submission_uuid, DB::table('pkp_revisions')->where('uuid', $base->uuid)->value('approved_submission_uuid'));
        $this->assertSame($base->final_document_uuid, DB::table('pkp_revisions')->where('uuid', $base->uuid)->value('final_document_uuid'));
        $after = DB::table('pkp')->where('uuid', $created->recordUuid)->first();
        $this->assertSame($before->first_submitted_at, $after->first_submitted_at);
        $this->assertSame($before->deadline_snapshot_at, $after->deadline_snapshot_at);
        $this->assertSame($before->lateness, $after->lateness);
        $this->assertSame($base->uuid, $after->official_revision_uuid);
        if ($base->source_document_uuid !== null) {
            Storage::disk('local')->assertExists(DB::table('pkp_documents')->where('uuid', $base->source_document_uuid)->value('storage_path'));
        }
        if ($nextSource === 'external') {
            $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$created->recordUuid.'/revisions/'.$next.'/documents', [
                'expected_version' => 5, 'kind' => 'source', 'declared_signed' => '0', 'file' => UploadedFile::fake()->createWithContent('new-unsigned.pdf', Pdf::loadHTML('<h1>New unsigned correction</h1>')->output()),
            ])->assertCreated();
            $newAttempt = $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => 6, 'revision_uuid' => $next])->assertOk()->json('data.submission_uuid');
            $this->actingAs($assessor)->postJson('/pkp/'.$created->recordUuid.'/decision', ['expected_version' => 7, 'revision_uuid' => $next, 'submission_uuid' => $newAttempt, 'decision' => 'approve'])->assertOk();
            $this->actingAs($owner)->getJson('/pkp?year=2030')->assertOk()->assertJsonPath('data.0.document_complete', false)->assertJsonPath('data.0.completeness', 'awaiting_signed_upload');
            $this->assertDatabaseHas('pkp_revisions', ['uuid' => $base->uuid, 'final_document_uuid' => $base->final_document_uuid]);
        }

    }

    public static function correctionModeSources(): array
    {
        return [['application'], ['external'], ['external', 'external']];
    }

    public function test_returned_numeric_changes_recalculate_only_the_new_submission(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        [$pkp, $revision, $submission] = $this->submitApplicationWithNotes($owner, 'Original numeric evidence');
        $original = DB::table('pkp_submissions')->where('uuid', $submission)->first();
        $this->actingAs($assessor)->postJson('/pkp/'.$pkp.'/decision', ['expected_version' => 3, 'revision_uuid' => $revision, 'submission_uuid' => $submission, 'decision' => 'return', 'reason' => 'Correct the actual quantity.'])->assertOk();
        $this->actingAs($owner)->putJson('/pkp/'.$pkp.'/revisions/'.$revision, ['expected_version' => 4, 'indicators' => [[
            'title' => 'Kinerja', 'activities' => [['description' => 'Corrected actual quantity', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '3', 'target_quality' => '100', 'realized_quantity' => '3']],
        ]]])->assertOk();
        $next = $this->actingAs($owner)->postJson('/pkp/'.$pkp.'/submit', ['expected_version' => 5, 'revision_uuid' => $revision])->assertOk()->json('data.submission_uuid');
        $newScore = json_decode(DB::table('pkp_submissions')->where('uuid', $next)->value('score_snapshot'), true);
        $this->assertSame('100', $newScore['overall_score']['numerator']);
        $this->assertSame('1', $newScore['overall_score']['denominator']);
        $this->assertSame($original->score_snapshot, DB::table('pkp_submissions')->where('uuid', $submission)->value('score_snapshot'));
        $oldScore = json_decode($original->score_snapshot, true);
        $this->assertSame('200', $oldScore['overall_score']['numerator']);
        $this->assertSame('3', $oldScore['overall_score']['denominator']);
    }

    public function test_returned_external_replacement_resets_declaration_and_never_inherits_completeness(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = app(PkpSubmissionService::class)->create($owner, 2030, 2, 'external');
        $upload = function (int $version, bool $signed, string $label) use ($owner, $created) {
            return $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$created->recordUuid.'/revisions/'.$created->pendingRevisionUuid.'/documents', [
                'expected_version' => $version, 'kind' => 'source', 'declared_signed' => $signed ? '1' : '0',
                'file' => UploadedFile::fake()->createWithContent('replacement-source.pdf', Pdf::loadHTML('<h1>'.e($label).'</h1>')->output()),
            ])->assertCreated()->json('data.document_uuid');
        };
        $firstDocument = $upload(1, true, 'Original declared signed source');
        $this->actingAs($owner)->getJson('/pkp?year=2030')->assertOk()->assertJsonPath('data.0.content_approved', false)->assertJsonPath('data.0.document_complete', false);
        $first = $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => 2, 'revision_uuid' => $created->pendingRevisionUuid])->assertOk()->json('data.submission_uuid');
        $this->actingAs($owner)->withHeader('Accept', 'text/html')->get('/pkp?year=2030')->assertOk()->assertSee('Belum disetujui');
        $this->actingAs($assessor)->postJson('/pkp/'.$created->recordUuid.'/decision', ['expected_version' => 3, 'revision_uuid' => $created->pendingRevisionUuid, 'submission_uuid' => $first, 'decision' => 'return', 'reason' => 'Replace the source assessment.'])->assertOk();
        $secondDocument = $upload(4, false, 'Fresh unsigned source');
        $second = $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => 5, 'revision_uuid' => $created->pendingRevisionUuid])->assertOk()->json('data.submission_uuid');
        $this->actingAs($assessor)->postJson('/pkp/'.$created->recordUuid.'/decision', ['expected_version' => 6, 'revision_uuid' => $created->pendingRevisionUuid, 'submission_uuid' => $second, 'decision' => 'approve'])->assertOk();
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $created->pendingRevisionUuid, 'source_document_uuid' => $secondDocument, 'final_document_uuid' => null]);
        $oldInput = json_decode(DB::table('pkp_submissions')->where('uuid', $first)->value('input_snapshot'), true);
        $newInput = json_decode(DB::table('pkp_submissions')->where('uuid', $second)->value('input_snapshot'), true);
        $this->assertTrue($oldInput['declared_signed']);
        $this->assertFalse($newInput['declared_signed']);
        $this->assertSame($firstDocument, $oldInput['source_document_uuid']);
        $this->assertSame($secondDocument, $newInput['source_document_uuid']);
        $this->assertDatabaseCount('pkp_document_reviews', 0);
        $this->actingAs($owner)->getJson('/pkp?year=2030')->assertOk()->assertJsonPath('data.0.document_complete', false)->assertJsonPath('data.0.completeness', 'awaiting_signed_upload');
        Storage::disk('local')->assertExists(DB::table('pkp_documents')->where('uuid', $firstDocument)->value('storage_path'));
    }

    public function test_missing_history_recovers_on_the_same_draft_after_effective_rows_are_added(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$historicalOwner, $assessor] = $this->createOwnerAndAssessor();
        $owner = $this->createUserWithRole('user');
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'pkp.manage-assessors', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 0, $assessor->pegawai->uuid, [$assessor->pegawai->uuid]);
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 2, 'application');
        $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, ['indicators' => [[
            'title' => 'History recovery', 'activities' => [['description' => 'Valid when history is available', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
        ]]]);
        $payload = ['expected_version' => 2, 'revision_uuid' => $created->pendingRevisionUuid];
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', $payload)->assertUnprocessable()->assertJsonValidationErrors(['identity.rank', 'identity.position', 'identity.work_unit']);
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 2, 'first_submitted_at' => null]);
        foreach ([RiwayatPangkat::class, RiwayatJabatan::class] as $model) {
            $row = $model::query()->where('pegawai_uuid', $historicalOwner->pegawai->uuid)->firstOrFail()->replicate();
            $row->uuid = (string) Str::uuid();
            $row->pegawai_uuid = $owner->pegawai->uuid;
            $row->save();
        }
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', $payload)->assertOk()->assertJsonPath('data.state', 'submitted');
        $this->assertDatabaseCount('pkp_submissions', 1);
    }

    public function test_future_period_returns_the_specified_business_code_and_field_feedback(): void
    {
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessor();
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 3, 'application');
        $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, ['indicators' => [[
            'title' => 'Future period', 'activities' => [['description' => 'Keep editable', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
        ]]]);
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => 2, 'revision_uuid' => $created->pendingRevisionUuid])
            ->assertUnprocessable()->assertJsonPath('error.code', 'period_not_submittable')->assertJsonValidationErrors('period');
        $this->assertDatabaseHas('pkp', ['uuid' => $created->recordUuid, 'version' => 2, 'first_submitted_at' => null]);
        $this->assertDatabaseCount('pkp_submissions', 0);
    }

    public function test_resubmission_preserves_exact_first_submission_and_deadline_bytes(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar')->microseconds(456789));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $service = app(PkpSubmissionService::class);
        $created = $service->create($owner, 2030, 2, 'application');
        DB::table('pkp_deadlines')->where('year', 2030)->where('month', 2)->update(['deadline_at' => '2030-02-28 09:00:00.123456']);
        $service->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, ['indicators' => [[
            'title' => 'Preserve original timestamps', 'activities' => [['description' => 'Exact time evidence', 'unit' => 'doc', 'target_credit' => '1', 'target_quantity' => '1', 'target_quality' => '100', 'realized_quantity' => '1']],
        ]]]);
        $submitted = $service->submit($owner, $created->recordUuid, $created->pendingRevisionUuid, 2, now()->toDateTimeImmutable());
        $original = DB::table('pkp')->where('uuid', $created->recordUuid)->first();
        $this->assertStringEndsWith('.456789', $original->first_submitted_at);
        $this->actingAs($assessor)->postJson('/pkp/'.$created->recordUuid.'/decision', [
            'expected_version' => 3, 'revision_uuid' => $created->pendingRevisionUuid,
            'submission_uuid' => $submitted->submissionUuid, 'decision' => 'return', 'reason' => 'Verify immutable first submission timestamps.',
        ])->assertOk();
        $this->actingAs($owner)->postJson('/pkp/'.$created->recordUuid.'/submit', ['expected_version' => 4, 'revision_uuid' => $created->pendingRevisionUuid])->assertOk();
        $after = DB::table('pkp')->where('uuid', $created->recordUuid)->first();
        $this->assertSame($original->first_submitted_at, $after->first_submitted_at);
        $this->assertSame($original->deadline_snapshot_at, $after->deadline_snapshot_at);
        $this->assertSame('late', $after->lateness);
    }

    public function test_identity_refresh_without_applicable_changes_is_conflict(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessor();
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

        $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/refresh-identity', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'reason' => 'Identity review',
        ])->assertConflict()
            ->assertJsonPath('error.code', 'identity_unchanged');
        $this->assertDatabaseHas('pkp_submissions', ['uuid' => $submitted->json('data.submission_uuid'), 'state' => 'submitted']);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 3]);
    }

    /** @return array{User, User} */
    private function createOwnerAndAssessor(): array
    {
        $owner = $this->createUserWithRole('user');
        $assessor = $this->createUserWithRole('user');
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->pegawai->uuid,
            [$assessor->pegawai->uuid]
        );
        $jabatan = Jabatan::factory()->create(['nama' => 'Hakim Madya']);
        RiwayatPangkat::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $owner->pegawai->uuid,
            'pangkat_golongan' => 'IV/a',
            'tmt' => '2029-01-01',
            'nomor_sk' => 'SK-RANK-1',
            'tanggal_sk' => '2029-01-01',
        ]);
        RiwayatJabatan::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $owner->pegawai->uuid,
            'jabatan_uuid' => $jabatan->uuid,
            'satuan_kerja' => 'PN Contoh',
            'tanggal_mulai' => '2029-01-01',
        ]);

        return [$owner, $assessor];
    }
}
