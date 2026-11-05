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

class PkpFinalReplacementTest extends SimpegTestCase
{
    public static function sourcePaths(): array
    {
        return [
            'application source' => ['application', false],
            'unsigned external source' => ['external', false],
            'signed external source' => ['external', true],
        ];
    }

    /** @dataProvider sourcePaths */
    public function test_replacement_preserves_accepted_a_until_b_is_inspected(string $source, bool $sourceSigned): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $case = $this->createApprovedCase($source, $sourceSigned);
        [$version, $documentAUuid] = $this->ensureAcceptedFinalA($case);
        $pdfB = $this->pdfBytes('replacement B');
        $uploadedB = $this->uploadSignedFinal($case, $version, 'signed-final-b.pdf', $pdfB, true);
        $documentBUuid = $uploadedB->json('data.document_uuid');

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => $documentAUuid,
            'pending_final_document_uuid' => $documentBUuid,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $this->assertDatabaseHas('pkp_documents', [
            'uuid' => $documentBUuid,
            'replacement_of_document_uuid' => $documentAUuid,
            'content_unchanged_declared' => true,
        ]);

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/documents/'.$documentBUuid.'/decision', [
            'expected_version' => $version + 1,
            'revision_uuid' => $case['revision_uuid'],
            'decision' => 'accept',
            'matches_approved_content' => true,
        ])->assertOk()
            ->assertJsonPath('data.completeness', 'complete');

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => $documentBUuid,
            'pending_final_document_uuid' => null,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $this->assertDatabaseHas('pkp_documents', ['uuid' => $documentAUuid]);
        $this->assertDatabaseHas('pkp_document_reviews', [
            'document_uuid' => $documentAUuid,
            'decision' => 'accept',
        ]);
        $this->assertDatabaseHas('pkp_document_reviews', [
            'document_uuid' => $documentBUuid,
            'decision' => 'accept',
            'replacement_base_document_uuid' => $documentAUuid,
            'matches_approved_content' => true,
        ]);
        $this->assertDatabaseHas('pkp_submissions', [
            'uuid' => $case['submission_uuid'],
            'state' => 'approved',
            'score_snapshot' => $case['score_snapshot'],
        ]);
    }

    /** @dataProvider sourcePaths */
    public function test_returned_replacement_keeps_accepted_a(string $source, bool $sourceSigned): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $case = $this->createApprovedCase($source, $sourceSigned);
        [$version, $documentAUuid, $expectedDocuments] = $this->ensureAcceptedFinalA($case);
        $uploadedB = $this->uploadSignedFinal($case, $version, 'returned-replacement-b.pdf', $this->pdfBytes('replacement B'), true);
        $documentBUuid = $uploadedB->json('data.document_uuid');

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/documents/'.$documentBUuid.'/decision', [
            'expected_version' => $version + 1,
            'revision_uuid' => $case['revision_uuid'],
            'decision' => 'return',
            'reason' => 'The replacement scan is incomplete.',
        ])->assertOk()
            ->assertJsonPath('data.completeness', 'complete');

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => $documentAUuid,
            'pending_final_document_uuid' => null,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $this->assertDatabaseHas('pkp_document_reviews', [
            'document_uuid' => $documentBUuid,
            'decision' => 'return',
            'reason' => 'The replacement scan is incomplete.',
            'replacement_base_document_uuid' => $documentAUuid,
        ]);
    }

    /** @dataProvider sourcePaths */
    public function test_replacement_declarations_are_required_and_rejection_preserves_a(string $source, bool $sourceSigned): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $case = $this->createApprovedCase($source, $sourceSigned);
        [$version, $documentAUuid, $expectedDocuments] = $this->ensureAcceptedFinalA($case);
        $pdfB = $this->pdfBytes('replacement B');

        foreach ([null, false] as $contentUnchanged) {
            $payload = [
                'expected_version' => $version,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('rejected-replacement-b.pdf', $pdfB),
            ];
            if ($contentUnchanged !== null) {
                $payload['content_unchanged'] = $contentUnchanged ? '1' : '0';
            }

            $this->actingAs($case['owner'])->withHeader('Accept', 'application/json')
                ->post('/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/documents', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'content_revision_required');
        }

        $this->assertDatabaseCount('pkp_documents', $expectedDocuments);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => $documentAUuid,
            'pending_final_document_uuid' => null,
            'approved_submission_uuid' => $case['submission_uuid'],
        ]);
        $documentAPath = DB::table('pkp_documents')->where('uuid', $documentAUuid)->value('storage_path');
        Storage::disk('local')->assertExists($documentAPath);
        $this->assertCount($expectedDocuments, Storage::disk('local')->allFiles('pkp/documents'));
    }

    public function test_inspector_must_confirm_replacement_and_false_confirmation_keeps_a(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $case = $this->createApprovedCase('application', false);
        [$version, $documentAUuid] = $this->ensureAcceptedFinalA($case);
        $uploadedB = $this->uploadSignedFinal($case, $version, 'signed-final-b.pdf', $this->pdfBytes('replacement B'), true);
        $documentBUuid = $uploadedB->json('data.document_uuid');
        foreach ([null, false] as $matchesApprovedContent) {
            $payload = [
                'expected_version' => $version + 1,
                'revision_uuid' => $case['revision_uuid'],
                'decision' => 'accept',
            ];
            if ($matchesApprovedContent !== null) {
                $payload['matches_approved_content'] = $matchesApprovedContent ? '1' : '0';
            }

            $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/documents/'.$documentBUuid.'/decision', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'content_not_confirmed');
        }

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'final_document_uuid' => $documentAUuid,
            'pending_final_document_uuid' => $documentBUuid,
        ]);
        $this->assertDatabaseCount('pkp_document_reviews', 1);
    }

    public function test_owner_form_requires_unchanged_content_declaration_for_final_replacement(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $case = $this->createApprovedCase('application', false);
        $this->ensureAcceptedFinalA($case);

        $this->actingAs($case['owner'])->withHeader('Accept', 'text/html')->get('/pkp/'.$case['pkp_uuid'])
            ->assertOk()
            ->assertSee('name="content_unchanged"', false)
            ->assertSee('Saya mengonfirmasi konten PKP tidak berubah.');
    }

    /** @param array{owner: User, assessor: User, pkp_uuid: string, revision_uuid: string, submission_uuid: string, score_snapshot: ?string, source: string, source_signed: bool, expected_documents: int, document_a_path: string} $case
     * @return array{int, string, int}
     */
    private function ensureAcceptedFinalA(array $case): array
    {
        $revision = DB::table('pkp_revisions')->where('uuid', $case['revision_uuid'])->first();
        if ($revision->final_document_uuid !== null) {
            $documentAUuid = $revision->final_document_uuid;

            return [4, $documentAUuid, $case['expected_documents']];
        }

        $uploadedA = $this->uploadSignedFinal($case, 4, 'signed-final-a.pdf', $this->pdfBytes('accepted A'), false);
        $documentAUuid = $uploadedA->json('data.document_uuid');
        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/documents/'.$documentAUuid.'/decision', [
            'expected_version' => 5,
            'revision_uuid' => $case['revision_uuid'],
            'decision' => 'accept',
            'matches_approved_content' => true,
        ])->assertOk();

        return [6, $documentAUuid, $case['expected_documents'] + 1];
    }

    /** @param array{owner: User, assessor: User, pkp_uuid: string, revision_uuid: string} $case */
    private function uploadSignedFinal(array $case, int $version, string $filename, string $bytes, bool $replacement)
    {
        $payload = [
            'expected_version' => $version,
            'kind' => 'signed_final',
            'file' => UploadedFile::fake()->createWithContent($filename, $bytes),
        ];
        if ($replacement) {
            $payload['content_unchanged'] = '1';
        }

        return $this->actingAs($case['owner'])->withHeader('Accept', 'application/json')
            ->post('/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/documents', $payload)
            ->assertCreated();
    }

    private function pdfBytes(string $label): string
    {
        return Pdf::loadHTML('<html><body><h1>'.e($label).'</h1></body></html>')->output();
    }

    /** @return array{owner: User, assessor: User, pkp_uuid: string, revision_uuid: string, submission_uuid: string, score_snapshot: string, source: string, source_signed: bool, expected_documents: int, document_a_path: string} */
    private function createApprovedCase(string $source, bool $sourceSigned): array
    {
        $this->travelTo(Carbon::create(2030, 2, 10, 12, 0, 0, 'Asia/Makassar'));
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
            'source' => $source,
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $version = 1;
        $expectedDocuments = 0;
        if ($source === 'external') {
            $sourceDocument = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
                '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
                [
                    'expected_version' => 1,
                    'kind' => 'source',
                    'declared_signed' => $sourceSigned ? '1' : '0',
                    'file' => UploadedFile::fake()->createWithContent('external-source.pdf', $this->pdfBytes('external source')),
                ]
            )->assertCreated();
            $version = 2;
            $expectedDocuments = 1;
        } else {
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
            $version = 2;
        }

        $submitted = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => $version,
            'revision_uuid' => $revisionUuid,
        ])->assertOk();
        $submissionUuid = $submitted->json('data.submission_uuid');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => $version + 1,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submissionUuid,
            'decision' => 'approve',
        ])->assertOk();

        $revision = DB::table('pkp_revisions')->where('uuid', $revisionUuid)->first();

        return [
            'owner' => $owner,
            'assessor' => $assessor,
            'pkp_uuid' => $pkpUuid,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submissionUuid,
            'score_snapshot' => DB::table('pkp_submissions')->where('uuid', $submissionUuid)->value('score_snapshot'),
            'source' => $source,
            'source_signed' => $sourceSigned,
            'expected_documents' => $expectedDocuments,
            'document_a_path' => $revision->final_document_uuid === null
                ? ''
                : DB::table('pkp_documents')->where('uuid', $revision->final_document_uuid)->value('storage_path'),
        ];
    }
}
