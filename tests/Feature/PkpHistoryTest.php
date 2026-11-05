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
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpHistoryTest extends SimpegTestCase
{
    public function test_returned_assessor_reads_only_its_frozen_attempt_and_source_not_live_owner_edits(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', ['year' => 2030, 'month' => 2, 'source' => 'external'])->assertCreated();
        $pkp = $created->json('data.pkp_uuid');
        $revision = $created->json('data.revision_uuid');
        $originalBytes = $this->pdfBytes('original reviewed source');
        $firstFile = $this->uploadSource($owner, $pkp, $revision, 1, 'original.pdf', $originalBytes)->json('data.document_uuid');
        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkp.'/submit', ['expected_version' => 2, 'revision_uuid' => $revision])->assertOk();
        $firstAttempt = $submitted->json('data.submission_uuid');
        $firstInstant = DB::table('pkp')->where('uuid', $pkp)->value('first_submitted_at');
        $this->actingAs($assessor)->postJson('/pkp/'.$pkp.'/decision', [
            'expected_version' => 3, 'revision_uuid' => $revision, 'submission_uuid' => $firstAttempt,
            'decision' => 'return', 'reason' => 'Replace the source document.',
        ])->assertOk();
        $newFile = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkp.'/revisions/'.$revision.'/documents', [
            'expected_version' => 4, 'kind' => 'source', 'declared_signed' => '0',
            'file' => UploadedFile::fake()->createWithContent('private-new.pdf', $this->pdfBytes('PRIVATE NEW SOURCE')),
        ])->assertCreated()->json('data.document_uuid');
        $this->actingAs($owner)->putJson('/pkp/'.$pkp.'/revisions/'.$revision, ['expected_version' => 5, 'notes' => 'PRIVATE LIVE NOTES'])->assertOk();
        $historyUrl = '/pkp/'.$pkp.'?revision_uuid='.$revision.'&submission_uuid='.$firstAttempt;

        $this->actingAs($assessor)->getJson($historyUrl)->assertOk()
            ->assertJsonPath('data.historical', true)->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.documents.source.uuid', $firstFile)
            ->assertJsonPath('data.capabilities.decide', false)->assertJsonPath('data.capabilities.upload_source', false)
            ->assertJsonCount(1, 'data.history.attempts')->assertDontSee('PRIVATE LIVE NOTES')->assertDontSee('private-new.pdf');
        $this->actingAs($owner)->getJson($historyUrl)->assertOk()->assertJsonPath('data.capabilities.submit', false)->assertJsonPath('data.capabilities.upload_source', false);
        $this->actingAs($owner)->getJson('/pkp/'.$pkp.'?submission_uuid='.$firstAttempt)->assertUnprocessable();
        $this->actingAs($assessor)->get('/pkp/'.$pkp.'/documents/'.$firstFile.'/download')->assertOk()->assertStreamedContent($originalBytes);
        $this->actingAs($assessor)->get('/pkp/'.$pkp.'/documents/'.$newFile.'/download')->assertForbidden();
        $ownerHistory = $this->actingAs($owner)->getJson('/pkp/'.$pkp)->assertOk()
            ->assertJsonPath('data.first_submitted_at', $firstInstant)->assertJsonPath('data.lateness', 'deadline_not_set')
            ->assertJsonPath('data.deadline_snapshot_at', null)->assertJsonCount(1, 'data.history.attempts')
            ->assertJsonPath('data.history.attempts.0.input_snapshot.source_document_uuid', $firstFile);
        $this->assertArrayHasKey('events', $ownerHistory->json('data.history'));
        $this->actingAs($owner)->withHeader('Accept', 'text/html')->get($historyUrl)->assertOk()->assertSee('Riwayat PKP')->assertDontSee('PRIVATE LIVE NOTES');
        $this->actingAs($owner)->getJson('/pkp/'.$pkp.'?revision_uuid='.$revision.'&submission_uuid='.(string) Str::uuid())->assertNotFound();

        $newAssessor = $this->createUserWithRole('user');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach (['pkp.manage-assessors', 'pkp.view-all', 'pkp.manage-deadlines'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        }
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 1, $newAssessor->pegawai->uuid, [$assessor->pegawai->uuid, $newAssessor->pegawai->uuid]);
        $this->actingAs($owner)->putJson('/pkp/'.$pkp.'/revisions/'.$revision, ['expected_version' => 6, 'assessor_pegawai_uuid' => $newAssessor->pegawai->uuid])->assertOk();
        $newAttempt = $this->actingAs($owner)->postJson('/pkp/'.$pkp.'/submit', ['expected_version' => 7, 'revision_uuid' => $revision])->assertOk()->json('data.submission_uuid');
        app(\App\Services\PkpDeadlineService::class)->configure($admin, 2030, 2, 0, '2030-02-01T00:00:00');
        $this->actingAs($assessor)->getJson('/pkp/'.$pkp)->assertOk()->assertJsonPath('data.submission_uuid', $firstAttempt)
            ->assertJsonCount(1, 'data.history.attempts')->assertDontSee('PRIVATE LIVE NOTES')->assertDontSee($newAttempt);
        $this->actingAs($newAssessor)->getJson($historyUrl)->assertForbidden();
        $this->actingAs($admin)->getJson('/pkp/'.$pkp)->assertOk()->assertJsonCount(2, 'data.history.attempts')
            ->assertJsonPath('data.lateness', 'deadline_not_set')->assertJsonPath('data.deadline_snapshot_at', null);
        $foreign = $this->createUserWithRole('user');
        $this->actingAs($foreign)->getJson($historyUrl)->assertForbidden();
    }

    public function test_archived_signed_source_and_approved_attempt_remain_authorized_and_private(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionOneUuid = $created->json('data.revision_uuid');
        $sourceOneBytes = $this->pdfBytes('signed source revision one');
        $sourceOne = $this->uploadSource($owner, $pkpUuid, $revisionOneUuid, 1, 'revision-one.pdf', $sourceOneBytes);
        $submissionOne = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionOneUuid,
        ])->assertOk();
        $submissionOneUuid = $submissionOne->json('data.submission_uuid');
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionOneUuid,
            'submission_uuid' => $submissionOneUuid,
            'decision' => 'approve',
        ])->assertOk();
        $sourceOneUuid = $sourceOne->json('data.document_uuid');
        $sourceOnePath = DB::table('pkp_documents')->where('uuid', $sourceOneUuid)->value('storage_path');
        Storage::disk('local')->assertExists($sourceOnePath);

        $correction = app(PkpSubmissionService::class)->revise($owner, $pkpUuid, 4, $revisionOneUuid, 'external');
        $revisionTwoUuid = $correction->pendingRevisionUuid;
        $sourceTwoBytes = $this->pdfBytes('signed source revision two');
        $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionTwoUuid.'/documents',
            [
                'expected_version' => 5,
                'kind' => 'source',
                'declared_signed' => '1',
                'file' => UploadedFile::fake()->createWithContent('revision-two.pdf', $sourceTwoBytes),
            ]
        )->assertCreated();
        $submissionTwo = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 6,
            'revision_uuid' => $revisionTwoUuid,
        ])->assertOk();
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 7,
            'revision_uuid' => $revisionTwoUuid,
            'submission_uuid' => $submissionTwo->json('data.submission_uuid'),
            'decision' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('pkp', [
            'uuid' => $pkpUuid,
            'official_revision_uuid' => $revisionTwoUuid,
            'pending_revision_uuid' => null,
        ]);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionOneUuid,
            'approved_submission_uuid' => $submissionOneUuid,
            'final_document_uuid' => $sourceOneUuid,
        ]);
        $this->assertDatabaseHas('pkp_submissions', [
            'uuid' => $submissionOneUuid,
            'state' => 'approved',
            'source_document_uuid' => $sourceOneUuid,
        ]);

        $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/documents/'.$sourceOneUuid.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertStreamedContent($sourceOneBytes);
        $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionOneUuid.'/pdf')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertStreamedContent($sourceOneBytes);
        $otherOwner = $this->createUserWithRole('user');
        $this->actingAs($otherOwner)->get('/pkp/'.$pkpUuid.'/documents/'.$sourceOneUuid.'/download')->assertForbidden();
        Storage::disk('local')->assertExists($sourceOnePath);
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid)->assertOk()
            ->assertJsonCount(2, 'data.history.attempts')->assertJsonCount(2, 'data.history.document_reviews')
            ->assertJsonCount(2, 'data.history.documents')->assertJsonMissingPath('data.history.documents.0.storage_path');
        $this->actingAs($owner)->getJson('/pkp/'.$pkpUuid.'?revision_uuid='.$revisionOneUuid)->assertOk()
            ->assertJsonPath('data.historical', true)->assertJsonPath('data.submission_uuid', $submissionOneUuid)
            ->assertJsonPath('data.capabilities.submit', false)->assertJsonPath('data.capabilities.upload_final', false);
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

    private function uploadSource(User $owner, string $pkpUuid, string $revisionUuid, int $version, string $filename, string $bytes)
    {
        return $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => $version,
                'kind' => 'source',
                'declared_signed' => '1',
                'file' => UploadedFile::fake()->createWithContent($filename, $bytes),
            ]
        )->assertCreated();
    }

    private function pdfBytes(string $title): string
    {
        return Pdf::loadHTML('<html><body><h1>'.e($title).'</h1></body></html>')->output();
    }
}
