<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use App\Services\PkpDocumentService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class PkpDocumentUploadTest extends SimpegTestCase
{
    public function test_html_invalid_pdf_redirects_with_metadata_and_no_file_input(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();

        $this->actingAs($owner)->from('/pkp/'.$pkpUuid)->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
            'expected_version' => 4,
            'kind' => 'signed_final',
            'file' => UploadedFile::fake()->createWithContent('invalid.pdf', 'not a PDF'),
        ])->assertRedirect('/pkp/'.$pkpUuid)->assertSessionHasErrors('file')
            ->assertSessionHas('_old_input.kind', 'signed_final')->assertSessionMissing('_old_input.file');

        $this->assertDatabaseCount('pkp_documents', 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 4]);
        Storage::disk('local')->assertDirectoryEmpty('pkp/documents');
    }

    public function test_html_inspector_confirmation_failure_preserves_the_pending_candidate(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')->assertOk()->getContent();
        $candidate = app(PkpDocumentService::class)->store($owner, $pkpUuid, $revisionUuid, 4, 'signed_final', UploadedFile::fake()->createWithContent('candidate.pdf', $bytes));
        $assessorUuid = \Illuminate\Support\Facades\DB::table('pkp_revisions')->where('uuid', $revisionUuid)->value('inspector_pegawai_uuid');
        $assessor = \App\Models\Pegawai::query()->findOrFail($assessorUuid)->user;

        $this->actingAs($assessor)->from('/pkp/'.$pkpUuid)->post('/pkp/'.$pkpUuid.'/documents/'.$candidate->documentUuid.'/decision', [
            'expected_version' => 5,
            'revision_uuid' => $revisionUuid,
            'decision' => 'accept',
            'matches_approved_content' => '0',
        ])->assertRedirect('/pkp/'.$pkpUuid)->assertSessionHasErrors('matches_approved_content')
            ->assertSessionHas('_old_input.decision', 'accept');

        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 5]);
        $this->assertDatabaseHas('pkp_revisions', ['uuid' => $revisionUuid, 'pending_final_document_uuid' => $candidate->documentUuid, 'final_document_uuid' => null]);
        $this->assertDatabaseCount('pkp_document_reviews', 0);
    }

    /** @dataProvider parserLimits */
    public function test_parser_limits_return_422_and_preserve_the_accepted_document(string $check, int $delay): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')->assertOk()->getContent();
        $service = app(PkpDocumentService::class);
        $accepted = $service->store($owner, $pkpUuid, $revisionUuid, 4, 'signed_final', UploadedFile::fake()->createWithContent('accepted.pdf', $bytes));
        $assessorUuid = \Illuminate\Support\Facades\DB::table('pkp_revisions')->where('uuid', $revisionUuid)->value('inspector_pegawai_uuid');
        $assessor = \App\Models\Pegawai::query()->findOrFail($assessorUuid)->user;
        $service->decide($assessor, $pkpUuid, $accepted->documentUuid, $revisionUuid, 5, 'accept', null, true);
        $binary = tempnam(sys_get_temp_dir(), 'pkp-limit-');
        $this->assertNotFalse($binary);
        file_put_contents($binary, '#!'.PHP_BINARY."\n<?php\nusleep(".$delay.");\nswitch (\$argv[1]) {\n"
            ."case '--version': echo 'qpdf version 12.4.0'; break;\ncase '--requires-password': exit(2);\n"
            ."case '--check': ".$check." break;\ncase '--show-npages': echo '1'; break;\n}\n");
        chmod($binary, 0700);
        config(['pkp.qpdf_binary' => $binary]);
        try {
            $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
                'expected_version' => 6,
                'kind' => 'signed_final',
                'content_unchanged' => '1',
                'file' => UploadedFile::fake()->createWithContent('replacement.pdf', $bytes),
            ])->assertUnprocessable()->assertJsonPath('error.code', 'pdf_validation_limit')->assertDontSee('PRIVATE_DIAGNOSTIC');
            $this->assertDatabaseCount('pkp_documents', 1);
            $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 6]);
            $this->assertDatabaseHas('pkp_revisions', ['uuid' => $revisionUuid, 'final_document_uuid' => $accepted->documentUuid, 'pending_final_document_uuid' => null]);
            $this->assertCount(1, Storage::disk('local')->allFiles('pkp/documents'));
            $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/documents/'.$accepted->documentUuid.'/download')->assertOk()->assertStreamedContent($bytes);
        } finally {
            unlink($binary);
        }
    }

    public static function parserLimits(): array
    {
        return [
            'output' => ["fwrite(STDERR, str_repeat('PRIVATE_DIAGNOSTIC', 5000));", 0],
            'operation timeout' => ['usleep(6000000);', 0],
            'total budget' => ['', 4000000],
        ];
    }

    public function test_unavailable_parser_returns_503_without_binding_a_file(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')->assertOk()->getContent();
        config(['pkp.qpdf_binary' => '/missing/pkp/qpdf']);

        $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
            'expected_version' => 4,
            'kind' => 'signed_final',
            'file' => UploadedFile::fake()->createWithContent('candidate.pdf', $bytes),
        ])->assertStatus(503)->assertJsonPath('error.code', 'pdf_validator_unavailable');

        $this->assertDatabaseCount('pkp_documents', 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 4]);
        Storage::disk('local')->assertDirectoryEmpty('pkp/documents');
    }

    public function test_current_assessor_can_download_source_and_reassignment_revokes_previous_access(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $otherPkpUuid, $approvedRevisionUuid] = $this->createApprovedApplicationPkp();
        $assessorUuid = \Illuminate\Support\Facades\DB::table('pkp_revisions')->where('uuid', $approvedRevisionUuid)->value('assessor_pegawai_uuid');
        $assessor = \App\Models\Pegawai::query()->findOrFail($assessorUuid)->user;
        $bytes = $this->actingAs($owner)->get('/pkp/'.$otherPkpUuid.'/revisions/'.$approvedRevisionUuid.'/pdf')->assertOk()->getContent();
        $created = $this->actingAs($owner)->postJson('/pkp', ['year' => 2030, 'month' => 1, 'source' => 'external'])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $uploaded = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents', [
            'expected_version' => 1,
            'kind' => 'source',
            'file' => UploadedFile::fake()->createWithContent('source.pdf', $bytes),
        ])->assertCreated();
        $documentUuid = $uploaded->json('data.document_uuid');
        $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', ['expected_version' => 2, 'revision_uuid' => $revisionUuid])->assertOk();
        $url = '/pkp/'.$pkpUuid.'/documents/'.$documentUuid.'/download';

        $this->actingAs($assessor)->get($url)->assertOk()->assertStreamedContent($bytes);
        $replacement = $this->createUserWithRole('user');
        $this->actingAs($replacement)->get($url)->assertForbidden();
        $this->actingAs($owner)->get('/pkp/'.$otherPkpUuid.'/documents/'.$documentUuid.'/download')->assertNotFound();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach (['pkp.manage-assessors', 'pkp.reassign'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]));
        }
        app(PkpAssessorSettingsService::class)->configure($admin, $owner->pegawai->uuid, 1, $assessorUuid, [$assessorUuid, $replacement->pegawai->uuid]);
        $this->actingAs($admin)->postJson('/pkp/'.$pkpUuid.'/reassign-assessor', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'assessor_pegawai_uuid' => $replacement->pegawai->uuid,
            'reason' => 'The original assessor is unavailable.',
        ])->assertOk();
        $this->actingAs($assessor)->get($url)->assertForbidden();
        $this->actingAs($replacement)->get($url)->assertOk()->assertStreamedContent($bytes);
        $this->actingAs($owner)->get($url)->assertOk()->assertStreamedContent($bytes);
    }

    public function test_document_metadata_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('pkp_documents'));
    }

    public function test_signed_final_service_persists_inspected_private_candidate(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();
        $pdf = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')
            ->assertOk()
            ->getContent();
        $file = UploadedFile::fake()->createWithContent('signed-final.pdf', $pdf);

        $result = app(PkpDocumentService::class)->store(
            $owner,
            $pkpUuid,
            $revisionUuid,
            4,
            'signed_final',
            $file,
            false
        );

        $document = \Illuminate\Support\Facades\DB::table('pkp_documents')->where('uuid', $result->documentUuid)->first();
        $this->assertSame('application/pdf', $document->detected_mime);
        $this->assertSame(hash('sha256', $pdf), $document->sha256);
        // The generated application PDF includes its calculation appendix on page two.
        $this->assertSame(2, (int) $document->page_count);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $revisionUuid,
            'pending_final_document_uuid' => $result->documentUuid,
            'final_document_uuid' => null,
        ]);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 5]);
        $this->assertTrue(Storage::disk('local')->exists($document->storage_path));
        $this->assertFalse(Storage::disk('public')->exists($document->storage_path));

        $download = app(PkpDocumentService::class)->download($owner, $pkpUuid, $result->documentUuid);
        $this->assertStringContainsString('private', $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));

        $otherOwner = $this->createUserWithRole('user');
        try {
            app(PkpDocumentService::class)->download($otherOwner, $pkpUuid, $result->documentUuid);
            $this->fail('A different employee cannot download this private PKP file.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_corrupt_pdf_is_rejected_and_staged_private_file_is_removed(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();

        $response = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('corrupt.pdf', 'not a PDF'),
            ]
        )->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_pdf');

        Storage::disk('local')->assertDirectoryEmpty('pkp/documents');
        $this->assertDatabaseCount('pkp_documents', 0);
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'version' => 4]);
    }

    public function test_signed_pdf_over_ten_mib_is_rejected_at_the_http_boundary(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();

        $response = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
            ]
        )->assertUnprocessable();

        $this->assertDatabaseCount('pkp_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('pkp/documents');
    }

    public function test_readable_pdf_at_exact_ten_mib_is_accepted(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();
        $pdf = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')
            ->assertOk()
            ->getContent();
        $maximumBytes = 10 * 1024 * 1024;
        $xrefMarker = strrpos($pdf, 'startxref');
        $this->assertNotFalse($xrefMarker);
        $paddingSize = $maximumBytes - strlen($pdf);
        $pdf = substr($pdf, 0, $xrefMarker).str_repeat(' ', $paddingSize).substr($pdf, $xrefMarker);
        $this->assertSame($maximumBytes, strlen($pdf));

        $response = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('ten-mib.pdf', $pdf),
            ]
        );
        $this->assertSame(201, $response->status(), json_encode($response->json()));
        $response->assertJsonPath('data.version', 5);

        $this->assertDatabaseHas('pkp_documents', [
            'revision_uuid' => $revisionUuid,
            'kind' => 'signed_final',
            'byte_size' => $maximumBytes,
        ]);
    }

    public function test_bootstrap_signed_final_upload_http_contract(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();
        $pdf = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')
            ->assertOk()
            ->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);

        $response = $this->actingAs($owner)->withHeader('Accept', 'application/json')->post(
            '/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/documents',
            [
                'expected_version' => 4,
                'kind' => 'signed_final',
                'file' => UploadedFile::fake()->createWithContent('signed-final.pdf', $pdf),
            ]
        )->assertCreated();

        $documentUuid = $response->json('data.document_uuid');
        $this->assertNotEmpty($documentUuid);

        $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/documents/'.$documentUuid.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=signed-final.pdf');

        $otherOwner = $this->createUserWithRole('user');
        $this->actingAs($otherOwner)->get('/pkp/'.$pkpUuid.'/documents/'.$documentUuid.'/download')->assertForbidden();
        $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/documents/'.(string) Str::uuid().'/download')->assertNotFound();
    }

    /** @return array{User, string, string} */
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
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submitted->json('data.submission_uuid'),
            'decision' => 'approve',
        ])->assertOk();

        return [$owner, $pkpUuid, $revisionUuid];
    }
}
