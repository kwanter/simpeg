<?php

namespace Tests\Unit\Support;

use App\Support\IzinType;
use PHPUnit\Framework\TestCase;

class IzinTypeTest extends TestCase
{
    public function test_all_returns_canonical_jenis_list(): void
    {
        $all = IzinType::all();

        $this->assertContains('Izin Sakit', $all);
        $this->assertContains('Izin Keluar Kantor', $all);
        $this->assertContains('Izin Pulang Cepat', $all);
        $this->assertContains('Izin Tidak Masuk Kerja', $all);
        $this->assertContains('Izin Lainnya', $all);
        $this->assertCount(10, $all);
    }

    public function test_single_level_jenis_are_keluar_kantor_and_pulang_cepat(): void
    {
        $this->assertTrue(IzinType::isSingleLevel('Izin Keluar Kantor'));
        $this->assertTrue(IzinType::isSingleLevel('Izin Pulang Cepat'));
        $this->assertFalse(IzinType::isSingleLevel('Izin Tidak Masuk Kerja'));
        $this->assertFalse(IzinType::isSingleLevel('Izin Sakit'));
    }

    public function test_same_day_jenis_match_single_level(): void
    {
        $this->assertTrue(IzinType::isSameDay('Izin Keluar Kantor'));
        $this->assertTrue(IzinType::isSameDay('Izin Pulang Cepat'));
        $this->assertFalse(IzinType::isSameDay('Izin Tidak Masuk Kerja'));
    }

    public function test_max_workdays_limited_jenis(): void
    {
        $this->assertSame(2, IzinType::maxWorkdays('Izin Tidak Masuk Kerja'));
        $this->assertNull(IzinType::maxWorkdays('Izin Sakit'));
        $this->assertNull(IzinType::maxWorkdays('Izin Keluar Kantor'));
    }

    public function test_pdf_template_selection(): void
    {
        $this->assertSame('izin.pdf-keluar-kantor', IzinType::pdfTemplate('Izin Keluar Kantor'));
        $this->assertSame('izin.pdf-keluar-kantor', IzinType::pdfTemplate('Izin Pulang Cepat'));
        $this->assertSame('izin.pdf-tidak-masuk', IzinType::pdfTemplate('Izin Tidak Masuk Kerja'));
        $this->assertSame('izin.pdf', IzinType::pdfTemplate('Izin Sakit'));
        $this->assertSame('izin.pdf', IzinType::pdfTemplate('Izin Lainnya'));
    }
}
