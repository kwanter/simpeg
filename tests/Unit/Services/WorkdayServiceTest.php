<?php

namespace Tests\Unit\Services;

use App\Models\HariLibur;
use App\Services\WorkdayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class WorkdayServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    public function test_counts_only_weekdays(): void
    {
        $count = WorkdayService::countWorkdays('2025-01-06', '2025-01-10');
        $this->assertEquals(5, $count);
    }

    public function test_excludes_weekends(): void
    {
        $count = WorkdayService::countWorkdays('2025-01-06', '2025-01-13');
        $this->assertEquals(6, $count);
    }

    public function test_excludes_database_holidays(): void
    {
        HariLibur::factory()->create([
            'tanggal' => '2025-01-08',
            'uuid' => (string) Str::uuid(),
        ]);

        $count = WorkdayService::countWorkdays('2025-01-06', '2025-01-10');
        $this->assertEquals(4, $count);
    }

    public function test_single_weekday(): void
    {
        $count = WorkdayService::countWorkdays('2025-01-06', '2025-01-06');
        $this->assertEquals(1, $count);
    }

    public function test_single_weekend_day(): void
    {
        $count = WorkdayService::countWorkdays('2025-01-11', '2025-01-11');
        $this->assertEquals(0, $count);
    }

    public function test_multiple_holidays(): void
    {
        HariLibur::factory()->create([
            'tanggal' => '2025-01-07',
            'uuid' => (string) Str::uuid(),
        ]);
        HariLibur::factory()->create([
            'tanggal' => '2025-01-09',
            'uuid' => (string) Str::uuid(),
        ]);

        $count = WorkdayService::countWorkdays('2025-01-06', '2025-01-10');
        $this->assertEquals(3, $count);
    }
}
