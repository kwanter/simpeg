<?php

namespace Tests\Unit\Services;

use App\Models\Cuti;
use App\Models\CutiBalance;
use App\Models\Pegawai;
use App\Services\CutiApprovalService;
use App\Services\CutiBalanceService;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class CutiApprovalServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    private CutiApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CutiApprovalService(new CutiBalanceService);
    }

    private function makeCuti(Pegawai $pegawai, array $attrs = []): Cuti
    {
        $year = now()->year;
        $start = $attrs['tanggal_mulai'] ?? Carbon::create($year, 5, 1);
        $end = $attrs['tanggal_selesai'] ?? Carbon::create($year, 5, 5);

        return Cuti::create(array_merge([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Tahunan',
            'tanggal_mulai' => $start,
            'tanggal_selesai' => $end,
            'lama_cuti' => WorkdayService::countWorkdays($start, $end),
            'alasan' => 'test',
            'alamat_selama_cuti' => 'x',
            'no_hp_selama_cuti' => 'x',
            'pimpinan_uuid' => null,
            'atasan_pimpinan_uuid' => null,
            'status' => 'Pending',
        ], $attrs));
    }

    public function test_apply_verifikator_approval_moves_status(): void
    {
        $pegawai = Pegawai::factory()->create();
        $cuti = $this->makeCuti($pegawai);

        $result = $this->service->applyVerifikator($cuti, 'Disetujui', 'ok');

        $this->assertSame('Disetujui Verifikator', $result->status);
        $this->assertSame('Disetujui', $result->status_verifikator);
        $this->assertSame('ok', $result->catatan_verifikator);
    }

    public function test_apply_verifikator_rejection_moves_status(): void
    {
        $pegawai = Pegawai::factory()->create();
        $cuti = $this->makeCuti($pegawai);

        $result = $this->service->applyVerifikator($cuti, 'Ditolak', 'reason');

        $this->assertSame('Ditolak Verifikator', $result->status);
    }

    public function test_apply_pimpinan_approval_advances_status(): void
    {
        $pegawai = Pegawai::factory()->create();
        $cuti = $this->makeCuti($pegawai, ['status' => 'Disetujui Verifikator']);
        $pimpinans = Pegawai::factory()->create();

        $result = $this->service->applyPimpinan($cuti, $pimpinans, 'Disetujui', 'lgtm');

        $this->assertSame('Disetujui Pimpinan', $result->status);
        $this->assertSame($pimpinans->uuid, $result->pimpinan_uuid);
    }

    public function test_pimpinan_approval_deducts_balance_for_annual_leave(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);
        $cuti = $this->makeCuti($pegawai, ['status' => 'Disetujui Verifikator']);
        $pimpinans = Pegawai::factory()->create();

        $this->service->applyPimpinan($cuti, $pimpinans, 'Disetujui', 'ok');

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame($cuti->lama_cuti, $balance->used_days);
    }

    public function test_pimpinan_approval_does_not_deduct_non_annual_leave(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);
        $cuti = $this->makeCuti($pegawai, [
            'status' => 'Disetujui Verifikator',
            'jenis_cuti' => 'Cuti Sakit',
        ]);
        $pimpinans = Pegawai::factory()->create();

        $this->service->applyPimpinan($cuti, $pimpinans, 'Disetujui', 'ok');

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(0, $balance->used_days);
    }

    public function test_pimpinan_rejection_does_not_deduct_balance(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);
        $cuti = $this->makeCuti($pegawai, ['status' => 'Disetujui Verifikator']);
        $pimpinans = Pegawai::factory()->create();

        $this->service->applyPimpinan($cuti, $pimpinans, 'Ditolak', 'no');

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(0, $balance->used_days);
    }

    public function test_apply_atasan_pimpinan_approval(): void
    {
        $pegawai = Pegawai::factory()->create();
        $cuti = $this->makeCuti($pegawai, ['status' => 'Disetujui Pimpinan']);
        $atasan = Pegawai::factory()->create();

        $result = $this->service->applyAtasanPimpinan($cuti, $atasan, 'Disetujui', 'final');

        $this->assertSame('Disetujui Atasan Pimpinan', $result->status);
        $this->assertSame($atasan->uuid, $result->atasan_pimpinan_uuid);
    }

    public function test_atasan_pimpinan_rejection_refunds_annual_leave_balance(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 5,
            'carried_over' => 0,
        ]);
        $cuti = $this->makeCuti($pegawai, [
            'status' => 'Disetujui Pimpinan',
            'jenis_cuti' => 'Cuti Tahunan',
        ]);
        $atasan = Pegawai::factory()->create();

        $this->service->applyAtasanPimpinan($cuti, $atasan, 'Ditolak', 'no');

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(5 - $cuti->lama_cuti, $balance->used_days); // refund only this cuti duration
    }

    public function test_atasan_pimpinan_rejection_does_not_refund_non_annual_leave(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 5,
            'carried_over' => 0,
        ]);
        $cuti = $this->makeCuti($pegawai, [
            'status' => 'Disetujui Pimpinan',
            'jenis_cuti' => 'Cuti Sakit',
        ]);
        $atasan = Pegawai::factory()->create();

        $this->service->applyAtasanPimpinan($cuti, $atasan, 'Ditolak', 'no');

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(5, $balance->used_days);
    }
}
