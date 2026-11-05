<?php

namespace Tests\Feature;

use App\Models\Jabatan;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpDocumentFailureTest extends SimpegTestCase
{
    public function test_storage_write_failure_returns_503_without_losing_accepted_evidence(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        [$owner, $assessor, $pkpUuid, $revisionUuid] = $this->createApprovedApplication();
        $service = app(PkpDocumentService::class);
        $bytes = $this->pdfBytes('accepted evidence');
        $accepted = $service->store($owner, $pkpUuid, $revisionUuid, 4, 'signed_final', UploadedFile::fake()->createWithContent('accepted.pdf', $bytes));
        $service->decide($assessor, $pkpUuid, $accepted->documentUuid, $revisionUuid, 5, 'accept', null, true);
        $disk = Storage::disk('local');
        $failingDisk = \Mockery::mock($disk);
        $failingDisk->shouldReceive('putFileAs')->andThrow(\League\Flysystem\UnableToWriteFile::atLocation('PRIVATE_STORAGE_PATH'));
        Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);

        $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
            'expected_version' => 6,
            'kind' => 'signed_final',
            'content_unchanged' => '1',
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', $this->pdfBytes('replacement')),
        ])->assertStatus(503)->assertJsonPath('error.code', 'document_storage_failed')->assertDontSee('PRIVATE_STORAGE_PATH');

        $this->assertDatabaseCount('pkp_documents', 1);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 6]);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $revisionUuid, 'final_document_uuid' => $accepted->documentUuid, 'pending_final_document_uuid' => null]);
        $this->assertCount(1, $disk->allFiles('pkp/documents'));
        $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/documents/'.$accepted->documentUuid.'/download')->assertOk()->assertStreamedContent($bytes);
    }

    public function test_metadata_database_failure_returns_safe_503_and_preserves_accepted_evidence(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        [$owner, $assessor, $pkpUuid, $revisionUuid] = $this->createApprovedApplication();
        $service = app(PkpDocumentService::class);
        $bytes = $this->pdfBytes('accepted evidence');
        $accepted = $service->store($owner, $pkpUuid, $revisionUuid, 4, 'signed_final', UploadedFile::fake()->createWithContent('accepted.pdf', $bytes));
        $service->decide($assessor, $pkpUuid, $accepted->documentUuid, $revisionUuid, 5, 'accept', null, true);
        DB::unprepared("CREATE TRIGGER reject_pkp_document BEFORE INSERT ON pkp_documents BEGIN SELECT RAISE(ABORT, 'PRIVATE_METADATA_FAILURE'); END;");

        $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
            'expected_version' => 6,
            'kind' => 'signed_final',
            'content_unchanged' => '1',
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', $this->pdfBytes('replacement')),
        ])->assertStatus(503)->assertJsonPath('error.code', 'database_unavailable')->assertDontSee('PRIVATE_METADATA_FAILURE');

        $this->assertDatabaseCount('pkp_documents', 1);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 6]);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $revisionUuid, 'final_document_uuid' => $accepted->documentUuid, 'pending_final_document_uuid' => null]);
        $this->assertDatabaseCount('pkp_document_reviews', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('pkp/documents'));
        $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/documents/'.$accepted->documentUuid.'/download')->assertOk()->assertStreamedContent($bytes);
    }

    public function test_failed_replacement_metadata_insert_preserves_accepted_a_and_removes_staging_b(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        [$owner, $assessor, $pkpUuid, $revisionUuid, $submissionUuid] = $this->createApprovedApplication();
        $service = app(PkpDocumentService::class);
        $bytesA = $this->pdfBytes('accepted A');
        $acceptedA = $service->store(
            $owner,
            $pkpUuid,
            $revisionUuid,
            4,
            'signed_final',
            UploadedFile::fake()->createWithContent('accepted-a.pdf', $bytesA)
        );
        $documentAUuid = $acceptedA->documentUuid;
        $service->decide(
            $assessor,
            $pkpUuid,
            $documentAUuid,
            $revisionUuid,
            5,
            'accept',
            null,
            true
        );
        $pathA = DB::table('pkp_documents')->where('uuid', $documentAUuid)->value('storage_path');
        Storage::disk('local')->assertExists($pathA);
        DB::unprepared("CREATE TRIGGER reject_pkp_document BEFORE INSERT ON pkp_documents BEGIN SELECT RAISE(ABORT, 'metadata insert failure'); END;");

        try {
            $service->store(
                $owner,
                $pkpUuid,
                $revisionUuid,
                6,
                'signed_final',
                UploadedFile::fake()->createWithContent('replacement-b.pdf', $this->pdfBytes('replacement B')),
                false,
                true
            );
            $this->fail('A database metadata failure must abort the replacement upload.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('metadata insert failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('pkp_documents', 1);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'final_document_uuid' => $documentAUuid,
            'pending_final_document_uuid' => null,
            'approved_submission_uuid' => $submissionUuid,
        ]);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 6]);
        $this->assertDatabaseHas('pkp_document_reviews', ['document_uuid' => $documentAUuid, 'decision' => 'accept']);
        Storage::disk('local')->assertExists($pathA);
        $this->assertCount(1, Storage::disk('local')->allFiles('pkp/documents'));
    }

    /** @return array{User, User, string, string, string} */
    private function createApprovedApplication(): array
    {
        $this->travelTo(CarbonImmutable::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
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

        $submissions = app(PkpSubmissionService::class);
        $created = $submissions->create($owner, 2030, 2, 'application');
        $submissions->save($owner, $created->recordUuid, $created->pendingRevisionUuid, 1, [
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
        ]);
        $submitted = $submissions->submit($owner, $created->recordUuid, $created->pendingRevisionUuid, 2, new \DateTimeImmutable('2030-03-01T04:00:00Z'));
        app(PkpAssessmentService::class)->decide(
            $assessor,
            $created->recordUuid,
            $created->pendingRevisionUuid,
            $submitted->submissionUuid,
            3,
            'approve',
            null,
            new \DateTimeImmutable('2030-03-01T04:00:00Z')
        );

        return [$owner, $assessor, $created->recordUuid, $created->pendingRevisionUuid, $submitted->submissionUuid];
    }

    private function pdfBytes(string $title): string
    {
        return Pdf::loadHTML('<html><body><h1>'.e($title).'</h1></body></html>')->output();
    }
}
