<?php

namespace Tests\Unit\Support;

use App\Support\CutiType;
use PHPUnit\Framework\TestCase;

class CutiTypeTest extends TestCase
{
    public function test_all_returns_canonical_jenis_list(): void
    {
        $all = CutiType::all();

        $this->assertContains('Cuti Tahunan', $all);
        $this->assertContains('Cuti Sakit', $all);
        $this->assertContains('Cuti Melahirkan', $all);
        $this->assertContains('Cuti Alasan Penting', $all);
        $this->assertContains('Cuti Besar', $all);
        $this->assertCount(5, $all);
    }

    public function test_constants_match_list(): void
    {
        $this->assertSame('Cuti Tahunan', CutiType::TAHUNAN);
        $this->assertSame('Cuti Sakit', CutiType::SAKIT);
        $this->assertSame('Cuti Melahirkan', CutiType::MELAHIRKAN);
        $this->assertSame('Cuti Alasan Penting', CutiType::ALASAN_PENTING);
        $this->assertSame('Cuti Besar', CutiType::BESAR);
    }

    public function test_requires_balance_returns_true_only_for_tahunan(): void
    {
        $this->assertTrue(CutiType::requiresBalance('Cuti Tahunan'));
        $this->assertFalse(CutiType::requiresBalance('Cuti Sakit'));
        $this->assertFalse(CutiType::requiresBalance('Cuti Besar'));
    }

    public function test_requires_eligibility_check(): void
    {
        $this->assertTrue(CutiType::requiresEligibilityCheck('Cuti Tahunan'));
        $this->assertTrue(CutiType::requiresEligibilityCheck('Cuti Besar'));
        $this->assertFalse(CutiType::requiresEligibilityCheck('Cuti Sakit'));
        $this->assertFalse(CutiType::requiresEligibilityCheck('Cuti Melahirkan'));
    }
}
