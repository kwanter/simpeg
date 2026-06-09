<?php

namespace Tests\Unit\Services;

use App\Models\Cuti;
use App\Models\Pegawai;
use App\Services\CutiBalanceService;
use App\Services\CutiEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class CutiEligibilityServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    private CutiEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CutiEligibilityService(new CutiBalanceService);
    }

    public function test_annual_leave_passes_when_balance_sufficient(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        \App\Models\CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);

        $error = $this->service->checkAnnualLeave(
            $pegawai->uuid,
            Carbon::create($year, 5, 1),
            Carbon::create($year, 5, 5)
        );

        $this->assertNull($error);
    }

    public function test_annual_leave_fails_when_balance_insufficient(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        \App\Models\CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 10,
            'carried_over' => 0,
        ]);

        $error = $this->service->checkAnnualLeave(
            $pegawai->uuid,
            Carbon::create($year, 5, 1),
            Carbon::create($year, 5, 10) // ~8 workdays
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Sisa cuti', $error);
    }

    public function test_annual_leave_fails_when_cuti_besar_exists(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        \App\Models\CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);

        Cuti::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Besar',
            'tanggal_mulai' => Carbon::create($year, 1, 1),
            'tanggal_selesai' => Carbon::create($year, 1, 5),
            'lama_cuti' => 5,
            'alasan' => 'test',
            'alamat_selama_cuti' => 'x',
            'no_hp_selama_cuti' => 'x',
            'pimpinan_uuid' => null,
            'atasan_pimpinan_uuid' => null,
            'status' => 'Disetujui Atasan Pimpinan',
        ]);

        $error = $this->service->checkAnnualLeave(
            $pegawai->uuid,
            Carbon::create($year, 5, 1),
            Carbon::create($year, 5, 5)
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Cuti Besar', $error);
    }

    public function test_cuti_besar_requires_tanggal_masuk(): void
    {
        $pegawai = Pegawai::factory()->create(['tanggal_masuk' => null]);

        $error = $this->service->checkCutiBesar(
            $pegawai,
            Carbon::now(),
            Carbon::now()->addDays(5)
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Tanggal masuk', $error);
    }

    public function test_cuti_besar_requires_min_5_years_service(): void
    {
        $pegawai = Pegawai::factory()->create([
            'tanggal_masuk' => Carbon::now()->subYears(3)->format('Y-m-d'),
        ]);

        $error = $this->service->checkCutiBesar(
            $pegawai,
            Carbon::now(),
            Carbon::now()->addDays(5)
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('5 tahun', $error);
    }

    public function test_cuti_besar_capped_at_90_workdays(): void
    {
        $pegawai = Pegawai::factory()->create([
            'tanggal_masuk' => Carbon::now()->subYears(10)->format('Y-m-d'),
        ]);

        $error = $this->service->checkCutiBesar(
            $pegawai,
            Carbon::create(now()->year, 1, 1),
            Carbon::create(now()->year, 12, 31) // ~261 workdays
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('3 bulan', $error);
    }

    public function test_cuti_besar_fails_when_cuti_tahunan_exists(): void
    {
        $pegawai = Pegawai::factory()->create([
            'tanggal_masuk' => Carbon::now()->subYears(10)->format('Y-m-d'),
        ]);
        $year = now()->year;

        Cuti::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Tahunan',
            'tanggal_mulai' => Carbon::create($year, 1, 1),
            'tanggal_selesai' => Carbon::create($year, 1, 5),
            'lama_cuti' => 5,
            'alasan' => 'test',
            'alamat_selama_cuti' => 'x',
            'no_hp_selama_cuti' => 'x',
            'pimpinan_uuid' => null,
            'atasan_pimpinan_uuid' => null,
            'status' => 'Disetujui Atasan Pimpinan',
        ]);

        $error = $this->service->checkCutiBesar(
            $pegawai,
            Carbon::create($year, 6, 1),
            Carbon::create($year, 6, 5)
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Cuti Tahunan', $error);
    }

    public function test_cuti_besar_fails_when_duplicate_in_year(): void
    {
        $pegawai = Pegawai::factory()->create([
            'tanggal_masuk' => Carbon::now()->subYears(10)->format('Y-m-d'),
        ]);
        $year = now()->year;

        Cuti::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Besar',
            'tanggal_mulai' => Carbon::create($year, 1, 1),
            'tanggal_selesai' => Carbon::create($year, 1, 5),
            'lama_cuti' => 5,
            'alasan' => 'test',
            'alamat_selama_cuti' => 'x',
            'no_hp_selama_cuti' => 'x',
            'pimpinan_uuid' => null,
            'atasan_pimpinan_uuid' => null,
            'status' => 'Pending',
        ]);

        $error = $this->service->checkCutiBesar(
            $pegawai,
            Carbon::create($year, 6, 1),
            Carbon::create($year, 6, 5)
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString('Cuti Besar', $error);
    }

    public function test_annual_leave_allows_excluding_current_cuti_besar_during_update(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        \App\Models\CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);

        $cuti = Cuti::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Besar',
            'tanggal_mulai' => Carbon::create($year, 1, 1),
            'tanggal_selesai' => Carbon::create($year, 1, 5),
            'lama_cuti' => 5,
            'alasan' => 'test',
            'alamat_selama_cuti' => 'x',
            'no_hp_selama_cuti' => 'x',
            'pimpinan_uuid' => null,
            'atasan_pimpinan_uuid' => null,
            'status' => 'Pending',
        ]);

        $error = $this->service->checkAnnualLeave(
            $pegawai->uuid,
            Carbon::create($year, 5, 1),
            Carbon::create($year, 5, 5),
            $cuti->uuid
        );

        $this->assertNull($error);
    }

    public function test_cuti_besar_allows_excluding_current_record_during_update(): void
    {
        $pegawai = Pegawai::factory()->create([
            'tanggal_masuk' => Carbon::now()->subYears(10)->format('Y-m-d'),
        ]);
        $year = now()->year;

        $cuti = Cuti::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Besar',
            'tanggal_mulai' => Carbon::create($year, 1, 1),
            'tanggal_selesai' => Carbon::create($year, 1, 5),
            'lama_cuti' => 5,
            'alasan' => 'test',
            'alamat_selama_cuti' => 'x',
            'no_hp_selama_cuti' => 'x',
            'pimpinan_uuid' => null,
            'atasan_pimpinan_uuid' => null,
            'status' => 'Pending',
        ]);

        $error = $this->service->checkCutiBesar(
            $pegawai,
            Carbon::create($year, 6, 1),
            Carbon::create($year, 6, 5),
            $cuti->uuid
        );

        $this->assertNull($error);
    }
}
