<?php

namespace Tests\Unit\Services;

use App\Models\CutiBalance;
use App\Models\Pegawai;
use App\Services\CutiBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class CutiBalanceServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    private CutiBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CutiBalanceService;
    }

    public function test_get_or_create_balance_creates_with_defaults(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;

        $balance = $this->service->getOrCreateBalance($pegawai->uuid, $year);

        $this->assertSame(12, $balance->total_days);
        $this->assertSame(0, $balance->used_days);
        $this->assertSame(0, $balance->carried_over);
    }

    public function test_get_or_create_balance_carries_over_from_previous_year(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;

        // Create previous year balance with 8 remaining
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year - 1,
            'total_days' => 12,
            'used_days' => 4,
            'carried_over' => 0,
        ]);

        $balance = $this->service->getOrCreateBalance($pegawai->uuid, $year);

        $this->assertSame(6, $balance->carried_over); // min(6, 8)
        $this->assertSame(18, $balance->remaining_days); // 12 + 6 - 0
    }

    public function test_carried_over_capped_at_six(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;

        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year - 1,
            'total_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);

        $balance = $this->service->getOrCreateBalance($pegawai->uuid, $year);

        $this->assertSame(6, $balance->carried_over); // min(6, 12)
    }

    public function test_deduct_workdays_increments_used_days(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 2,
            'carried_over' => 0,
        ]);

        $this->service->deductWorkdays($pegawai->uuid, $year, 3);

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(5, $balance->used_days); // 2 + 3
    }

    public function test_refund_workdays_decrements_used_days(): void
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

        $this->service->refundWorkdays($pegawai->uuid, $year, 3);

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(2, $balance->used_days); // max(0, 5 - 3)
    }

    public function test_refund_does_not_go_below_zero(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 2,
            'carried_over' => 0,
        ]);

        $this->service->refundWorkdays($pegawai->uuid, $year, 10);

        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)->where('year', $year)->first();
        $this->assertSame(0, $balance->used_days);
    }

    public function test_remaining_days_calculation(): void
    {
        $pegawai = Pegawai::factory()->create();
        $year = now()->year;
        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 5,
            'carried_over' => 3,
        ]);

        $remaining = $this->service->remainingDays($pegawai->uuid, $year);

        $this->assertSame(10, $remaining); // 12 + 3 - 5
    }
}
