<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpDocumentInspectionTest extends SimpegTestCase
{
    public function test_document_review_storage_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('pkp_document_reviews'));
    }

    public function test_bootstrap_document_inspection_http_contract(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $case = $this->createApprovedApplicationPkp();
        $pdf = $this->actingAs($case['owner'])->get('/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/pdf')
            ->assertOk()
            ->getContent();
        $uploaded = $this->actingAs($case['owner'])->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('signed-final.pdf', $pdf),
            ]
        )->assertCreated();
        $documentUuid = $uploaded->json('data.document_uuid');

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/documents/'.$documentUuid.'/decision', [
            'expected_version' => 5,
            'revision_uuid' => $case['revision_uuid'],
            'decision' => 'accept',
            'matches_approved_content' => true,
        ])->assertOk()
            ->assertJsonPath('data.completeness', 'complete')
            ->assertJsonPath('data.version', 6);

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => $documentUuid,
            'pending_final_document_uuid' => null,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $this->assertDatabaseHas('pkp_document_reviews', [
            'document_uuid' => $documentUuid,
            'actor_user_uuid' => $case['assessor']->uuid,
            'decision' => 'accept',
            'basis' => 'post_approval_inspection',
            'approved_submission_uuid' => $case['submission_uuid'],
            'matches_approved_content' => true,
        ]);
        $this->assertDatabaseHas('pkp_submissions', [
            'uuid' => $case['submission_uuid'],
            'state' => 'approved',
        ]);
        $this->assertDatabaseHas('pkp_decisions', [
            'submission_uuid' => $case['submission_uuid'],
            'actor_user_uuid' => $case['assessor']->uuid,
            'decision' => 'approve',
        ]);
    }

    public function test_acceptance_requires_content_match_confirmation_and_preserves_pending_candidate(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $case = $this->createApprovedApplicationPkp();
        $pdf = $this->actingAs($case['owner'])->get('/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/pdf')
            ->assertOk()
            ->getContent();
        $uploaded = $this->actingAs($case['owner'])->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('signed-final.pdf', $pdf),
            ]
        )->assertCreated();
        $documentUuid = $uploaded->json('data.document_uuid');
        $url = '/pkp/'.$case['pkp_uuid'].'/documents/'.$documentUuid.'/decision';
        $payload = [
            'expected_version' => 5,
            'revision_uuid' => $case['revision_uuid'],
            'decision' => 'accept',
        ];

        $this->actingAs($case['assessor'])->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'content_not_confirmed');
        $this->actingAs($case['assessor'])->postJson($url, array_merge($payload, ['matches_approved_content' => false]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'content_not_confirmed');

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => null,
            'pending_final_document_uuid' => $documentUuid,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $this->assertDatabaseCount('pkp_document_reviews', 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $case['pkp_uuid'], 'version' => 5]);
    }

    public function test_inspector_can_return_candidate_with_reason_without_changing_content_approval(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $case = $this->createApprovedApplicationPkp();
        $pdf = $this->actingAs($case['owner'])->get('/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/pdf')
            ->assertOk()
            ->getContent();
        $uploaded = $this->actingAs($case['owner'])->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('signed-final.pdf', $pdf),
            ]
        )->assertCreated();
        $documentUuid = $uploaded->json('data.document_uuid');
        $url = '/pkp/'.$case['pkp_uuid'].'/documents/'.$documentUuid.'/decision';
        $payload = [
            'expected_version' => 5,
            'revision_uuid' => $case['revision_uuid'],
            'decision' => 'return',
        ];

        $this->actingAs($case['assessor'])->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->actingAs($case['assessor'])->postJson($url, array_merge($payload, ['reason' => 'The approved content is not fully signed.']))
            ->assertOk()
            ->assertJsonPath('data.completeness', 'incomplete')
            ->assertJsonPath('data.version', 6);

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => null,
            'pending_final_document_uuid' => null,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $this->assertDatabaseHas('pkp_documents', ['uuid' => $documentUuid, 'revision_uuid' => $case['revision_uuid']]);
        $this->assertDatabaseHas('pkp_document_reviews', [
            'document_uuid' => $documentUuid,
            'decision' => 'return',
            'reason' => 'The approved content is not fully signed.',
        ]);
        $this->assertDatabaseHas('pkp_submissions', ['uuid' => $case['submission_uuid'], 'state' => 'approved']);
    }

    /** @return array{owner: User, assessor: User, pkp_uuid: string, revision_uuid: string, submission_uuid: string} */
    private function createApprovedApplicationPkp(): array
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
        $submissionUuid = $submitted->json('data.submission_uuid');
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submissionUuid,
            'decision' => 'approve',
        ])->assertOk();

        return [
            'owner' => $owner,
            'assessor' => $assessor,
            'pkp_uuid' => $pkpUuid,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submissionUuid,
        ];
    }
}
