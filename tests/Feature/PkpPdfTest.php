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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\SimpegTestCase;

class PkpPdfTest extends SimpegTestCase
{
    public function test_bootstrap_approved_application_pdf_http_contract(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp();

        $response = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
        $this->assertNotNull($assessor->pegawai);
    }

    public function test_archived_approved_application_remains_printable_only_by_authorized_actors(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp(null, 'ARCHIVED R1 NOTES');
        $correction = app(PkpSubmissionService::class)->revise($owner, $pkpUuid, 4, $revisionUuid, 'application');
        $next = $correction->pendingRevisionUuid;
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$next, [
            'expected_version' => 5, 'notes' => 'CURRENT R2 NOTES',
        ])->assertOk();
        $submission = $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 6, 'revision_uuid' => $next,
        ])->assertOk()->json('data.submission_uuid');
        $this->actingAs($assessor)->postJson('/pkp/'.$pkpUuid.'/decision', [
            'expected_version' => 7, 'revision_uuid' => $next,
            'submission_uuid' => $submission, 'decision' => 'approve',
        ])->assertOk();
        $this->assertDatabaseHas('pkp', ['uuid' => $pkpUuid, 'official_revision_uuid' => $next]);
        foreach ([$owner, $assessor] as $actor) {
            $response = $this->actingAs($actor)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $text = $this->pdfText($response->getContent());
            $this->assertStringContainsString('ARCHIVED R1 NOTES', $text);
            $this->assertStringNotContainsString('CURRENT R2 NOTES', $text);
            $this->actingAs($actor)->getJson('/pkp/'.$pkpUuid.'?revision_uuid='.$revisionUuid)
                ->assertOk()->assertJsonPath('data.capabilities.print', true)
                ->assertJsonPath('data.capabilities.upload_final', false);
        }
        $stranger = $this->createUserWithRole('user');
        $this->actingAs($stranger)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')->assertForbidden();
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 8, 'notes' => 'Cannot mutate approved history',
        ])->assertNotFound();
        $this->assertDatabaseMissing('pkp_revisions', ['uuid' => $revisionUuid, 'notes' => 'Cannot mutate approved history']);
    }

    public function test_approved_pdf_contains_frozen_notes_and_distinct_exact_calculations(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $activity = fn (string $description, string $realized) => [
            'description' => $description, 'unit' => 'dokumen', 'target_credit' => '1',
            'target_quantity' => '3', 'target_quality' => '100', 'realized_quantity' => $realized,
        ];
        $tree = [
            ['title' => 'Oracle First', 'activities' => [$activity('Oracle Two Thirds', '2'), $activity('Oracle Full', '3')]],
            ['title' => 'Oracle Second', 'activities' => [$activity('Oracle One Third', '1')]],
        ];
        [$owner, $assessor, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp($tree, 'FROZEN PDF NOTES');
        DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update(['notes' => 'LIVE NOTES MUST NOT LEAK']);
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')->assertOk()->getContent();
        $decoded = $this->pdfText($bytes);
        foreach (['FROZEN PDF NOTES', '66,666666...', '200/3', '250/3', '100/3', '175/3', '2/1'] as $text) {
            $this->assertTrue(str_contains($decoded, $text), 'Generated PDF text must contain '.$text);
        }
        $this->assertStringNotContainsString('LIVE NOTES MUST NOT LEAK', $decoded);
    }

    public function test_multipage_pdf_keeps_all_activity_text_repeated_headers_and_frozen_identity(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        $activities = [];
        for ($index = 1; $index <= 18; $index++) {
            $activities[] = [
                'description' => sprintf('ROW%02d ', $index).str_repeat('Uraian pekerjaan lengkap untuk pemeriksaan halaman. ', 10).sprintf(' END%02d', $index),
                'unit' => 'dokumen', 'target_credit' => '1', 'target_quantity' => '3',
                'target_quality' => '100', 'realized_quantity' => '2',
            ];
        }
        [$owner, $assessor, $pkpUuid, $revisionUuid] = $this->createApprovedApplicationPkp([
            ['title' => 'Multipage Evidence', 'activities' => $activities],
        ], 'MULTIPAGE FROZEN NOTES');
        $ownerName = $owner->pegawai->nama;
        $assessorName = $assessor->pegawai->nama;
        $owner->pegawai->update(['nama' => 'LIVE OWNER MUST NOT LEAK']);
        $assessor->pegawai->update(['nama' => 'LIVE ASSESSOR MUST NOT LEAK']);
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid.'/pdf')->assertOk()->getContent();
        $text = $this->pdfText($bytes);
        for ($index = 1; $index <= 18; $index++) {
            $this->assertStringContainsString(sprintf('ROW%02d', $index), $text);
            $this->assertStringContainsString(sprintf('END%02d', $index), $text);
        }
        foreach (['MULTIPAGE FROZEN NOTES', 'Lampiran perhitungan eksak', 'Nilai Keseluruhan:', 'Pejabat Penilai', 'Revisi 1', $ownerName, $assessorName] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringNotContainsString('LIVE OWNER MUST NOT LEAK', $text);
        $this->assertStringNotContainsString('LIVE ASSESSOR MUST NOT LEAK', $text);
        $normalized = preg_replace('/\s+/', ' ', $text);
        $this->assertGreaterThan(1, substr_count($normalized, 'Target Kuantitas'));
        $this->assertGreaterThan(1, substr_count($normalized, 'Nilai rasional eksak'));
        if ($evidencePath = getenv('PKP_PDF_EVIDENCE_PATH')) {
            file_put_contents($evidencePath, $bytes);
        }
    }

    public function test_maximum_description_title_and_unit_stay_inside_actual_pdf_page_bounds(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor, $pkp, $revision] = $this->createApprovedApplicationPkp([[
            'title' => str_repeat('W', 496).' TXZ',
            'activities' => [['description' => str_repeat('W', 1996).' DXZ', 'unit' => str_repeat('W', 96).' UXZ',
                'target_credit' => '1', 'target_quantity' => '3', 'target_quality' => '100', 'realized_quantity' => '2']],
        ]]);
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkp.'/revisions/'.$revision.'/pdf')->assertOk()->getContent();
        $qdf = $this->qdfBytes($bytes);
        preg_match_all('/BT\s+[-0-9.]+\s+([-0-9.]+)\s+Td/', $qdf, $positions);
        $this->assertNotEmpty($positions[1]);
        foreach ($positions[1] as $y) {
            $this->assertGreaterThanOrEqual(0, (float) $y, 'No PDF text baseline may be clipped below its A4 page.');
            $this->assertLessThanOrEqual(595.28, (float) $y);
        }
        $text = $this->pdfText($bytes);
        $this->assertGreaterThan(1, substr_count(preg_replace('/\s+/', ' ', $text), 'Target Kuantitas'));
        foreach (['TXZ', 'DXZ', 'UXZ', 'Pejabat Penilai', '200/3'] as $marker) {
            $this->assertStringContainsString($marker, $text);
        }
        if ($path = getenv('PKP_PDF_MAX_EVIDENCE_PATH')) {
            file_put_contents($path, $bytes);
        }
    }

    public function test_maximum_unbroken_notes_wrap_without_losing_characters(): void
    {
        $this->travelTo(Carbon::create(2030, 3, 1, 12, 0, 0, 'Asia/Makassar'));
        [$owner, $assessor, $pkp, $revision] = $this->createApprovedApplicationPkp(null, str_repeat('N', 4996).'ZZZQ');
        $bytes = $this->actingAs($owner)->get('/pkp/'.$pkp.'/revisions/'.$revision.'/pdf')->assertOk()->getContent();
        $runs = array_values(array_filter(explode("\n", $this->pdfText($bytes)), fn (string $text): bool => preg_match('/^[NZQ]+$/', $text) === 1));
        $this->assertNotEmpty($runs);
        $this->assertSame(4996, array_sum(array_map(fn (string $run): int => substr_count($run, 'N'), $runs)));
        $this->assertStringContainsString('ZZZQ', implode('', $runs));
        foreach ($runs as $run) {
            $this->assertLessThanOrEqual(200, strlen($run), 'A 9pt N-run longer than 200 characters cannot fit on A4 landscape.');
        }
        if ($path = getenv('PKP_PDF_NOTES_EVIDENCE_PATH')) {
            file_put_contents($path, $bytes);
        }
    }

    public function test_draft_cannot_be_printed(): void
    {
        $owner = $this->createUserWithRole('user');
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => 2030,
            'month' => 2,
            'source' => 'application',
        ])->assertCreated();

        $this->actingAs($owner)->get('/pkp/'.$created->json('data.pkp_uuid').'/revisions/'.$created->json('data.revision_uuid').'/pdf')
            ->assertConflict();
    }

    private function qdfBytes(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkp-pdf-');
        try {
            file_put_contents($path, $bytes);
            $process = new Process([config('pkp.qpdf_binary') ?: getenv('PKP_QPDF_BINARY'), '--qdf', '--object-streams=disable', $path, '-']);
            $process->mustRun();
            $decoded = $process->getOutput();
        } finally {
            unlink($path);
        }

        return $decoded;
    }

    private function pdfText(string $bytes): string
    {
        $decoded = $this->qdfBytes($bytes);
        // Dompdf's DejaVu text runs use UTF-16BE hex operands in QDF content streams.
        preg_match_all('/BT\s.*?\[<([0-9a-f]+)>\] TJ ET/s', $decoded, $runs);

        return implode("\n", array_map(fn (string $hex) => mb_convert_encoding(hex2bin($hex), 'UTF-8', 'UTF-16BE'), $runs[1]));
    }

    /** @return array{User, User, string, string} */
    private function createApprovedApplicationPkp(?array $tree = null, ?string $notes = null): array
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
            'notes' => $notes,
            'indicators' => $tree ?? [[
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

        return [$owner, $assessor, $pkpUuid, $revisionUuid];
    }
}
