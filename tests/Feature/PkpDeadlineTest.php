<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Permission;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\Role;
use App\Models\User;
use App\Services\PkpAssessorSettingsService;
use App\Services\PkpDeadlineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\SimpegTestCase;

class PkpDeadlineTest extends SimpegTestCase
{
    public function test_bootstrap_unlinked_admin_deadline_read_contract(): void
    {
        $admin = $this->adminWithoutPegawai();

        $this->actingAs($admin)->getJson('/pkp/deadlines/2030/4')
            ->assertOk()
            ->assertJsonPath('data.year', 2030)
            ->assertJsonPath('data.month', 4)
            ->assertJsonPath('data.deadline_local', null)
            ->assertJsonPath('data.deadline_at', null)
            ->assertJsonPath('data.version', 0);
    }

    public function test_unlinked_admin_can_configure_and_read_back_deadline_through_http_and_blade(): void
    {
        $admin = $this->adminWithoutPegawai();
        $url = '/pkp/deadlines/2030/4';

        $this->actingAs($admin)->putJson($url, [
            'expected_version' => 0,
            'deadline_local' => '2030-04-02T17:00:00',
        ])->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.deadline_local', '2030-04-02T17:00:00')
            ->assertJsonPath('data.deadline_at', '2030-04-02 09:00:00.000000');

        $this->actingAs($admin)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.deadline_local', '2030-04-02T17:00:00');

        $this->actingAs($admin)->get($url)
            ->assertOk()
            ->assertSee('Asia/Makassar')
            ->assertSee('name="expected_version" value="1"', false)
            ->assertSee('value="2030-04-02T17:00:00"', false);

        $this->actingAs($admin)->putJson($url, [
            'expected_version' => 1,
            'deadline_local' => '2030-04-02T17:00:00+08:00',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('pkp_deadlines', [
            'year' => 2030,
            'month' => 4,
            'version' => 1,
            'deadline_at' => '2030-04-02 09:00:00.000000',
        ]);
        $this->assertDatabaseCount('pkp_configuration_events', 1);
    }

    public function test_deadline_service_stores_local_makassar_value_as_utc_and_audits_it(): void
    {
        $admin = $this->adminWithoutPegawai();

        $result = app(PkpDeadlineService::class)->configure(
            $admin,
            2030,
            4,
            0,
            '2030-04-02T17:00:00'
        );

        $this->assertSame(1, $result->version);
        $this->assertSame('2030-04-02 09:00:00', $result->deadlineAt?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('pkp_deadlines', [
            'year' => 2030,
            'month' => 4,
            'deadline_at' => '2030-04-02 09:00:00.000000',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('pkp_configuration_events', [
            'kind' => 'deadline',
            'configuration_uuid' => $result->configurationUuid,
            'actor_user_uuid' => $admin->uuid,
        ]);
    }

    public function test_deadline_service_rejects_stale_versions_and_offset_timestamps(): void
    {
        $admin = $this->adminWithoutPegawai();
        $service = app(PkpDeadlineService::class);
        $service->configure($admin, 2030, 4, 0, '2030-04-02T17:00:00');

        try {
            $service->configure($admin, 2030, 4, 0, '2030-04-03T17:00:00');
            $this->fail('A stale configuration version must conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        try {
            $service->configure($admin, 2030, 4, 1, '2030-04-03T17:00:00+08:00');
            $this->fail('An offset-bearing deadline timestamp must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('deadline_local', $exception->errors());
        }

        $this->assertSame(1, DB::table('pkp_configuration_events')->where('kind', 'deadline')->count());
    }

    public function test_deadline_is_inclusive_and_later_edits_do_not_reclassify_submissions(): void
    {
        $admin = $this->adminWithoutPegawai();
        $deadlineService = app(PkpDeadlineService::class);
        $deadlineService->configure($admin, 2030, 2, 0, '2030-02-28T23:59:59');
        $assessor = $this->createUserWithRole('user');
        $deadline = Carbon::create(2030, 2, 28, 23, 59, 59, 'Asia/Makassar');
        $onTimeOwner = $this->createOwnerWithSettings($admin, $assessor->pegawai);
        $onTimePkpUuid = $this->createCompleteDraft($onTimeOwner, 2030, 2);
        $this->travelTo($deadline);
        $onTime = $this->submit($onTimeOwner, $onTimePkpUuid);
        $originalOnTime = DB::table('pkp')->where('uuid', $onTimePkpUuid)->first();
        $this->assertSame('on_time', $onTime->json('data.lateness'));

        $lateOwner = $this->createOwnerWithSettings($admin, $assessor->pegawai);
        $latePkpUuid = $this->createCompleteDraft($lateOwner, 2030, 2);
        $this->travelTo($deadline->copy()->addMicrosecond());
        $late = $this->submit($lateOwner, $latePkpUuid);
        $this->assertSame('late', $late->json('data.lateness'));

        $deadlineService->configure($admin, 2030, 2, 1, '2030-02-28T23:59:58');

        $this->assertDatabaseHas('pkp', [
            'uuid' => $onTimePkpUuid,
            'first_submitted_at' => $originalOnTime->first_submitted_at,
            'deadline_snapshot_at' => $originalOnTime->deadline_snapshot_at,
            'lateness' => 'on_time',
        ]);
        $this->assertDatabaseHas('pkp', [
            'uuid' => $latePkpUuid,
            'lateness' => 'late',
        ]);
    }

    private function adminWithoutPegawai(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-deadlines', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));
        $admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'pkp.manage-assessors', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        ));

        return $admin;
    }

    private function createOwnerWithSettings(User $admin, Pegawai $assessor): User
    {
        $owner = $this->createUserWithRole('user');
        app(PkpAssessorSettingsService::class)->configure(
            $admin,
            $owner->pegawai->uuid,
            0,
            $assessor->uuid,
            [$assessor->uuid]
        );
        $jabatan = Jabatan::factory()->create(['nama' => 'Hakim periode PKP']);
        RiwayatPangkat::query()->create([
            'pegawai_uuid' => $owner->pegawai->uuid,
            'pangkat_golongan' => 'IV/a',
            'tmt' => '2029-01-01',
            'nomor_sk' => 'SK-PKP',
            'tanggal_sk' => '2029-01-01',
        ]);
        RiwayatJabatan::query()->create([
            'pegawai_uuid' => $owner->pegawai->uuid,
            'jabatan_uuid' => $jabatan->uuid,
            'satuan_kerja' => 'PN Periode',
            'tanggal_mulai' => '2029-01-01',
        ]);

        return $owner;
    }

    private function createCompleteDraft(User $owner, int $year, int $month): string
    {
        $created = $this->actingAs($owner)->postJson('/pkp', [
            'year' => $year,
            'month' => $month,
            'source' => 'application',
        ])->assertCreated();
        $pkpUuid = $created->json('data.pkp_uuid');
        $revisionUuid = $created->json('data.revision_uuid');
        $this->actingAs($owner)->putJson('/pkp/'.$pkpUuid.'/revisions/'.$revisionUuid, [
            'expected_version' => 1,
            'indicators' => [[
                'title' => 'Kinerja',
                'activities' => [[
                    'description' => 'Target periodik',
                    'unit' => 'dokumen',
                    'target_credit' => '1.000000',
                    'target_quantity' => '1.0000',
                    'target_quality' => '100.0000',
                    'realized_quantity' => '1.0000',
                ]],
            ]],
        ])->assertOk();

        return $pkpUuid;
    }

    private function submit(User $owner, string $pkpUuid): \Illuminate\Testing\TestResponse
    {
        $revisionUuid = DB::table('pkp')->where('uuid', $pkpUuid)->value('pending_revision_uuid');

        return $this->actingAs($owner)->postJson('/pkp/'.$pkpUuid.'/submit', [
            'expected_version' => 2,
            'revision_uuid' => $revisionUuid,
        ])->assertOk();
    }
}
