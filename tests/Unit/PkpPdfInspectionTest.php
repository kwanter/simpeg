<?php

namespace Tests\Unit;

use App\Support\PdfInspection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PkpPdfInspectionTest extends TestCase
{
    public function test_inspection_result_exposes_read_only_validated_metadata(): void
    {
        $inspection = new PdfInspection(str_repeat('a', 64), 2048, 2, 'application/pdf', 'qpdf 12.4.2');

        $this->assertSame(str_repeat('a', 64), $inspection->sha256);
        $this->assertSame(2048, $inspection->byteSize);
        $this->assertSame(2, $inspection->pageCount);
        $this->assertSame('application/pdf', $inspection->detectedMime);
        $this->assertSame('qpdf 12.4.2', $inspection->validatorVersion);
        $this->assertTrue((new ReflectionProperty($inspection, 'sha256'))->isReadOnly());
        $this->assertTrue((new ReflectionProperty($inspection, 'pageCount'))->isReadOnly());
    }

    public function test_inspection_result_rejects_invalid_pdf_metadata(): void
    {
        foreach ([
            ['', 10, 1, 'application/pdf', 'qpdf 12.4.2'],
            [str_repeat('A', 64), 10, 1, 'application/pdf', 'qpdf 12.4.2'],
            [str_repeat('a', 64), 0, 1, 'application/pdf', 'qpdf 12.4.2'],
            [str_repeat('a', 64), 10, 0, 'application/pdf', 'qpdf 12.4.2'],
            [str_repeat('a', 64), 10, 1, 'text/plain', 'qpdf 12.4.2'],
        ] as [$sha256, $byteSize, $pageCount, $mime, $version]) {
            try {
                new PdfInspection($sha256, $byteSize, $pageCount, $mime, $version);
                $this->fail('Invalid inspection metadata must not produce a PdfInspection.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
