<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpExternalTest extends SimpegTestCase
{
    public function test_external_html_create_redirects_to_its_editable_draft(): void
    {
        [$owner] = $this->createOwnerAndAssessor();
        $assessorUuid = DB::table('pkp_assessor_settings')
            ->where('pegawai_uuid', $owner->pegawai->uuid)
            ->value('default_assessor_uuid');

        $response = $this->actingAs($owner)->post('/pkp', [
            '_token' => 'browser-csrf-token',
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
            'assessor_pegawai_uuid' => $assessorUuid,
        ]);

        $this->assertSame(303, $response->status(), json_encode(session('errors')?->getBag('default')->getMessages()));
        $this->assertDatabaseHas('pkp_revisions', [
            'source' => 'external',
            'state' => 'draft',
        ]);
    }

    public function test_declared_signed_external_pdf_can_be_submitted_without_activity_or_score_data(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $pdf = Pdf::loadHTML('<html><body><h1>Externally prepared PKP</h1></body></html>')->output();

        $uploaded = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 1,
                'kind' => 'source',
                'declared_signed' => '1',
                'file' => UploadedFile::fake()->createWithContent('external-source.pdf', $pdf),
            ]
        )->assertCreated();
        $sourceDocumentUuid = $uploaded->json('data.document_uuid');

        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk()
            ->assertJsonPath('data.state', 'submitted')
            ->assertJsonPath('data.version', 3);

        $submissionUuid = $submitted->json('data.submission_uuid');
        $submission = DB::table('pkp_submissions')->where('uuid', $submissionUuid)->first();
        $this->assertSame($sourceDocumentUuid, $submission->source_document_uuid);
        $this->assertNotNull($submission->source_sha256);
        $this->assertNull($submission->calculation_version);
        $this->assertNull($submission->score_snapshot);
        $this->assertSame([], json_decode($submission->input_snapshot, true, 512, JSON_THROW_ON_ERROR)['indicators']);
        $this->assertNotNull($assessor->pegawai);

        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submissionUuid,
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('data.state', 'approved');

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'source_document_uuid' => $sourceDocumentUuid,
            'final_document_uuid' => $sourceDocumentUuid,
            'pending_final_document_uuid' => null,
        ]);
        $this->assertDatabaseHas('pkp_document_reviews', [
            'document_uuid' => $sourceDocumentUuid,
            'decision' => 'accept',
            'basis' => 'content_approval_reuse',
            'approved_submission_uuid' => $submissionUuid,
            'matches_approved_content' => null,
        ]);
    }

    public function test_unsigned_external_source_does_not_become_accepted_final_at_content_approval(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $pdf = Pdf::loadHTML('<html><body><h1>Unsigned external PKP</h1></body></html>')->output();
        $uploaded = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 1,
                'kind' => 'source',
                'declared_signed' => '0',
                'file' => UploadedFile::fake()->createWithContent('external-unsigned.pdf', $pdf),
            ]
        )->assertCreated();
        $sourceDocumentUuid = $uploaded->json('data.document_uuid');
        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk();
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));

        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submitted->json('data.submission_uuid'),
            'decision' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'source_document_uuid' => $sourceDocumentUuid,
            'final_document_uuid' => null,
        ]);
        $this->assertDatabaseCount('pkp_document_reviews', 0);
    }

    public function test_external_submission_requires_source_pdf(): void
    {
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
        ])->assertCreated();

        $this->actingAs($owner)->postJson('/pkp/'.$created->json('data.pkp_uuid').'/submit', [
            'expected_version' => 1,
            'revision_uuid' => $created->json('data.revision_uuid'),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('pkp_submissions', 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $created->json('data.pkp_uuid'), 'version' => 1, 'first_submitted_at' => null]);
    }

    public function test_external_create_rejects_structured_activity_and_score_fields(): void
    {
        $owner = $this->createUserWithRole('user');

        $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
            'indicators' => [],
            'score_snapshot' => ['overall_score' => '100'],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('pkp', 0);
    }

    public function test_approved_external_pdf_streams_the_exact_source_bytes_without_scores(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor] = $this->createOwnerAndAssessor();
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'external',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $sourceBytes = Pdf::loadHTML('<html><body><h1>Immutable external source</h1></body></html>')->output();
        $source = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 1,
                'kind' => 'source',
                'declared_signed' => '1',
                'file' => UploadedFile::fake()->createWithContent('signed-source.pdf', $sourceBytes),
            ]
        )->assertCreated();

        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk();
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submitted->json('data.submission_uuid'),
            'decision' => 'approve',
        ])->assertOk();

        $response = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $response->assertStreamedContent($sourceBytes);
        $this->assertNull($submitted->json('data.score'));
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'source_document_uuid' => $source->json('data.document_uuid'),
            'final_document_uuid' => $source->json('data.document_uuid'),
        ]);
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
