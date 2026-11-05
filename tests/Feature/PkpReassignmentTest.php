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
use App\Services\PkpAssignmentService;
use App\Services\PkpDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\SimpegTestCase;

class PkpReassignmentTest extends SimpegTestCase
{
    public function test_bootstrap_content_reassignment_http_contract(): void
    {
        $case = $this->createSubmittedCase();

        $this->actingAs($case['admin'])->postJson('/pkp/'.$case['pkp_uuid'].'/reassign-assessor', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'assessor_pegawai_uuid' => $case['replacement']->pegawai->uuid,
            'reason' => 'Assessor assignment changed for availability.',
        ])->assertOk()
            ->assertJsonPath('data.assessor_pegawai_uuid', $case['replacement']->pegawai->uuid)
            ->assertJsonPath('data.version', 4);
    }

    public function test_bootstrap_inspector_reassignment_http_contract(): void
    {
        $case = $this->createSubmittedCase();
        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertOk();

        $this->actingAs($case['admin'])->postJson('/pkp/'.$case['pkp_uuid'].'/reassign-inspector', [
            'expected_version' => 4,
            'revision_uuid' => $case['revision_uuid'],
            'inspector_pegawai_uuid' => $case['replacement']->pegawai->uuid,
            'reason' => 'The assigned inspector is unavailable for review.',
        ])->assertOk()
            ->assertJsonPath('data.inspector_pegawai_uuid', $case['replacement']->pegawai->uuid)
            ->assertJsonPath('data.version', 5);
    }

    public function test_authorized_admin_sees_only_valid_reassignment_controls(): void
    {
        $case = $this->createSubmittedCase();
        $case['admin']->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.view-all', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        $this->actingAs($case['admin'])->get('/pkp/'.$case['pkp_uuid'])
            ->assertOk()
            ->assertSee('Alihkan penilaian konten')
            ->assertSee('action="'.route('pkp.reassign-assessor', $case['pkp_uuid']).'"', false)
            ->assertSee($case['replacement']->pegawai->nama)
            ->assertSee('Alasan reassignment');

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertOk();

        $this->actingAs($case['admin'])->get('/pkp/'.$case['pkp_uuid'])
            ->assertOk()
            ->assertSee('Alihkan pemeriksaan dokumen')
            ->assertSee('action="'.route('pkp.reassign-inspector', $case['pkp_uuid']).'"', false)
            ->assertSee($case['replacement']->pegawai->nama)
            ->assertDontSee('Alihkan penilaian konten');
    }

    public function test_content_reassignment_changes_current_authority_without_rewriting_attempt_history(): void
    {
        $case = $this->createSubmittedCase();
        $result = app(PkpAssignmentService::class)->reassignContent(
            $case['admin'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            3,
            $case['replacement']->pegawai->uuid,
            'Assessor assignment changed for availability.'
        );

        $this->assertSame(4, $result->version);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'assessor_pegawai_uuid' => $case['replacement']->pegawai->uuid,
            'state' => 'submitted',
        ]);
        $attempt = DB::table('pkp_submissions')->where('uuid', $case['submission_uuid'])->first();
        $this->assertSame($case['assessor']->pegawai->uuid, json_decode($attempt->assessor_snapshot, true)['pegawai_uuid']);

        $event = DB::table('pkp_events')->where('event_type', 'assessor_reassigned')->first();
        $this->assertNotNull($event);
        $this->assertSame($case['admin']->uuid, $event->actor_user_uuid);
        $this->assertSame($case['assessor']->pegawai->uuid, json_decode($event->before_json, true)['assessor_pegawai_uuid']);
        $this->assertSame($case['replacement']->pegawai->uuid, json_decode($event->after_json, true)['assessor_pegawai_uuid']);
        $this->assertSame('Assessor assignment changed for availability.', json_decode($event->after_json, true)['reason']);

        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 4,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertForbidden();
        $this->assertDatabaseCount('pkp_decisions', 0);

        $this->actingAs($case['replacement'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 4,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertOk();
        $this->assertDatabaseHas('pkp_decisions', [
            'submission_uuid' => $case['submission_uuid'],
            'actor_user_uuid' => $case['replacement']->uuid,
            'decision' => 'approve',
        ]);
        $revision = DB::table('pkp_revisions')->where('uuid', $case['revision_uuid'])->first();
        $this->assertSame($case['replacement']->pegawai->uuid, json_decode($revision->approved_assessor_snapshot, true)['pegawai_uuid']);
    }

    public function test_reassignment_rejects_unallowed_assessors_and_preserves_authority(): void
    {
        $case = $this->createSubmittedCase();
        $unlisted = Pegawai::factory()->create();

        try {
            app(PkpAssignmentService::class)->reassignContent(
                $case['admin'],
                $case['pkp_uuid'],
                $case['revision_uuid'],
                3,
                $unlisted->uuid,
                'Assessor is not on the allowed list.'
            );
            $this->fail('An unlisted employee cannot receive assessment authority.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('assessor_pegawai_uuid', $exception->errors());
        }

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'assessor_pegawai_uuid' => $case['assessor']->pegawai->uuid,
            'state' => 'submitted',
        ]);
        $this->assertDatabaseCount('pkp_events', 1);
        $this->assertDatabaseHas('pkp', ['uuid' => $case['pkp_uuid'], 'version' => 3]);
    }

    public function test_approved_content_cannot_be_reassigned(): void
    {
        $case = $this->createSubmittedCase();
        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertOk();

        try {
            app(PkpAssignmentService::class)->reassignContent(
                $case['admin'],
                $case['pkp_uuid'],
                $case['revision_uuid'],
                4,
                $case['replacement']->pegawai->uuid,
                'Approved content must remain attributed to its assessor.'
            );
            $this->fail('Approved content cannot be reassigned.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('revision_approved', $exception->getMessage());
        }

        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'assessor_pegawai_uuid' => $case['assessor']->pegawai->uuid,
            'state' => 'approved',
        ]);
        $this->assertDatabaseCount('pkp_events', 2);
    }

    public function test_inspector_reassignment_preserves_content_approval_and_moves_document_authority(): void
    {
        config(['pkp.qpdf_binary' => getenv('PKP_QPDF_BINARY')]);
        Storage::fake('local');
        $case = $this->createSubmittedCase();
        $this->actingAs($case['assessor'])->postJson('/pkp/'.$case['pkp_uuid'].'/decision', [
            'expected_version' => 3,
            'revision_uuid' => $case['revision_uuid'],
            'submission_uuid' => $case['submission_uuid'],
            'decision' => 'approve',
        ])->assertOk();
        $approvedRevision = DB::table('pkp_revisions')->where('uuid', $case['revision_uuid'])->first();
        $approvedSnapshot = $approvedRevision->approved_assessor_snapshot;
        $sourcePdf = $this->actingAs($case['owner'])->get('/pkp/'.$case['pkp_uuid'].'/revisions/'.$case['revision_uuid'].'/pdf')
            ->assertOk()
            ->getContent();
        $document = app(PkpDocumentService::class)->store(
            $case['owner'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            4,
            'signed_final',
            UploadedFile::fake()->createWithContent('signed-final.pdf', $sourcePdf)
        );

        $result = app(PkpAssignmentService::class)->reassignInspector(
            $case['admin'],
            $case['pkp_uuid'],
            $case['revision_uuid'],
            5,
            $case['replacement']->pegawai->uuid,
            'The assigned inspector is unavailable for document review.'
        );

        $this->assertSame(6, $result->version);
        $this->assertDatabaseHas('pkp_revisions', [
            'uuid' => $case['revision_uuid'],
            'inspector_pegawai_uuid' => $case['replacement']->pegawai->uuid,
            'approved_assessor_snapshot' => $approvedSnapshot,
            'approved_submission_uuid' => $case['submission_uuid'],
            'pending_final_document_uuid' => $document->documentUuid,
        ]);
        $this->assertSame([], app(PkpAssessmentService::class)->queue($case['assessor'], 'document'));
        $this->assertSame(1, count(app(PkpAssessmentService::class)->queue($case['replacement'], 'document')));
        $event = DB::table('pkp_events')->where('event_type', 'inspector_reassigned')->first();
        $this->assertNotNull($event);
        $this->assertSame($case['assessor']->pegawai->uuid, json_decode($event->before_json, true)['inspector_pegawai_uuid']);
        $this->assertSame($case['replacement']->pegawai->uuid, json_decode($event->after_json, true)['inspector_pegawai_uuid']);
    }

    /** @return array{admin: User, owner: User, assessor: User, replacement: User, pkp_uuid: string, revision_uuid: string, submission_uuid: string} */
    private function createSubmittedCase(): array
    {
        $this->travelTo(now('Asia/Makassar')->setDate(2030, 3, 1)->setTime(12, 0, 0));
        $owner = $this->createUserWithRole('user');
        $assessor = $this->createUserWithRole('user');
        $replacement = $this->createUserWithRole('user');
        $admin = $this->adminWithReassignmentPermission();
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->pegawai->uuid,
            [$assessor->pegawai->uuid, $replacement->pegawai->uuid]
        );
        $this->createIdentityHistory($owner->pegawai);

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
            'admin' => $admin,
            'owner' => $owner,
            'assessor' => $assessor,
            'replacement' => $replacement,
            'pkp_uuid' => $pkpUuid,
            'revision_uuid' => $revisionUuid,
            'submission_uuid' => $submitted->json('data.submission_uuid'),
        ];
    }

    private function adminWithReassignmentPermission(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.reassign', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.view-all', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        return $admin;
    }

    private function createIdentityHistory(Pegawai $pegawai): void
    {
        $jabatan = Jabatan::factory()->create(['nama' => 'Hakim Madya']);
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
