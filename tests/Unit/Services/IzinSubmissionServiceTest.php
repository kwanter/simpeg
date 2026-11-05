<?php

namespace Tests\Unit\Services;

use App\Models\Pegawai;
use App\Services\IzinSubmissionService;
use App\Support\IzinType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\SimpegTestCase;

class IzinSubmissionServiceTest extends SimpegTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): IzinSubmissionService
    {
        return new IzinSubmissionService;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'jenis_izin' => IzinType::KEPERLUAN_PRIBADI,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Keperluan pribadi',
        ], $overrides);
    }

    public function test_service_is_resolvable_from_the_container_without_arguments(): void
    {
        $this->assertInstanceOf(IzinSubmissionService::class, app(IzinSubmissionService::class));
    }

    public function test_service_exposes_exactly_the_three_contract_methods(): void
    {
        $publicMethods = array_map(
            fn (\ReflectionMethod $method) => $method->getName(),
            (new \ReflectionClass(IzinSubmissionService::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        sort($publicMethods);

        $this->assertSame(
            ['prepareForCreate', 'prepareForFullUpdate', 'resolveOwnerUuid'],
            $publicMethods,
            'This seam is deliberately minimal (AC-003). If a fourth public method is genuinely needed, amend AC-003 before relaxing this assertion - do not delete the assertion to make an addition pass.'
        );
    }

    public function test_prepare_for_create_pins_single_level_dates_and_zeroes_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $result = $this->service()->prepareForCreate($this->payload([
            'jenis_izin' => IzinType::KELUAR_KANTOR,
            'tanggal_mulai' => '2026-01-06',
            'tanggal_selesai' => '2026-01-07',
        ]));

        $this->assertSame(0, $result['jumlah_hari']);
        $this->assertSame('2026-01-05', $result['tanggal_mulai']);
        $this->assertSame('2026-01-05', $result['tanggal_selesai']);
    }

    public function test_prepare_for_create_sets_initial_approval_state_overriding_supplied_values(): void
    {
        $result = $this->service()->prepareForCreate($this->payload([
            'status' => 'Disetujui',
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Disetujui',
        ]));

        $this->assertSame('Diajukan', $result['status']);
        $this->assertSame('Belum Diverifikasi', $result['verifikasi_atasan']);
        $this->assertSame('Belum Diverifikasi', $result['verifikasi_pimpinan']);
    }

    public function test_prepare_for_create_passes_unrecognised_keys_through_unchanged(): void
    {
        $result = $this->service()->prepareForCreate($this->payload([
            'pegawai_uuid' => 'pegawai-uuid-value',
            'dokumen' => 'document.pdf',
        ]));

        $this->assertSame('pegawai-uuid-value', $result['pegawai_uuid']);
        $this->assertSame('document.pdf', $result['dokumen']);
        $this->assertSame('Keperluan pribadi', $result['alasan']);
    }

    public function test_prepare_for_create_accepts_exactly_two_workdays_for_tidak_masuk(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $result = $this->service()->prepareForCreate($this->payload([
            'jenis_izin' => IzinType::TIDAK_MASUK,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
        ]));

        $this->assertSame(2, $result['jumlah_hari']);
    }

    public function test_prepare_for_create_rejects_tidak_masuk_beyond_the_two_workday_cap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        try {
            $this->service()->prepareForCreate($this->payload([
                'jenis_izin' => IzinType::TIDAK_MASUK,
                'tanggal_mulai' => '2026-01-05',
                'tanggal_selesai' => '2026-01-07',
            ]));
            $this->fail('A three-workday Izin Tidak Masuk Kerja must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'tanggal_selesai' => ['Izin tidak masuk kerja maksimal 2 (dua) hari kerja.'],
            ], $exception->errors());
        }
    }

    public function test_prepare_for_full_update_rejects_tidak_masuk_beyond_the_two_workday_cap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        try {
            $this->service()->prepareForFullUpdate($this->payload([
                'jenis_izin' => IzinType::TIDAK_MASUK,
                'tanggal_mulai' => '2026-01-05',
                'tanggal_selesai' => '2026-01-07',
            ]));
            $this->fail('A three-workday Izin Tidak Masuk Kerja must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'tanggal_selesai' => ['Izin tidak masuk kerja maksimal 2 (dua) hari kerja.'],
            ], $exception->errors());
        }
    }

    public function test_prepare_for_full_update_omits_approval_and_letter_metadata(): void
    {
        $result = $this->service()->prepareForFullUpdate($this->payload([
            'status' => 'Disetujui',
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Disetujui',
            'tanggal_verifikasi_atasan' => '2026-01-01',
            'tanggal_verifikasi_pimpinan' => '2026-01-02',
            'no_surat_izin' => 'IZIN-2026-001',
        ]));

        foreach ([
            'status',
            'verifikasi_atasan',
            'verifikasi_pimpinan',
            'tanggal_verifikasi_atasan',
            'tanggal_verifikasi_pimpinan',
            'no_surat_izin',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $result);
        }
    }

    public function test_single_level_normalisation_does_not_query_working_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $hariLiburQueries = 0;
        DB::listen(function ($query) use (&$hariLiburQueries) {
            if (preg_match('/from ["`]?hari_libur["`]?/i', $query->sql)) {
                $hariLiburQueries++;
            }
        });

        $this->service()->prepareForCreate($this->payload([
            'jenis_izin' => IzinType::KELUAR_KANTOR,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-05',
        ]));

        $this->assertSame(
            0,
            $hariLiburQueries,
            'The single-level branch must not compute working days whose result it immediately discards (PERF-02).'
        );
    }

    public function test_resolve_owner_uuid_follows_the_privilege_and_emptiness_table(): void
    {
        $admin = $this->createUserWithRole('admin');
        $adminPegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();
        $superAdmin = $this->createUserWithRole('super-admin');
        $superAdminPegawai = Pegawai::where('nip', $superAdmin->nip)->firstOrFail();
        $user = $this->createUserWithRole('user');
        $userPegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $target = Pegawai::factory()->create();

        $service = $this->service();

        $this->assertSame($target->uuid, $service->resolveOwnerUuid($admin, $target->uuid));
        $this->assertSame($adminPegawai->uuid, $service->resolveOwnerUuid($admin, null));
        $this->assertSame($adminPegawai->uuid, $service->resolveOwnerUuid($admin, ''));

        $this->assertSame($target->uuid, $service->resolveOwnerUuid($superAdmin, $target->uuid));
        $this->assertSame($superAdminPegawai->uuid, $service->resolveOwnerUuid($superAdmin, null));

        $this->assertSame($userPegawai->uuid, $service->resolveOwnerUuid($user, $target->uuid));
        $this->assertSame($userPegawai->uuid, $service->resolveOwnerUuid($user, null));
        $this->assertSame($userPegawai->uuid, $service->resolveOwnerUuid($user, ''));
    }
}
