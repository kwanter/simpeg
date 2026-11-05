<?php

namespace Tests\Unit;

use App\Services\InvalidPdf;
use App\Services\PdfValidatorUnavailable;
use App\Services\PkpPdfValidator;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PkpPdfValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @dataProvider excessiveOutput */
    public function test_parser_rejects_output_above_the_combined_operation_limit(string $output): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $binary = $this->controlledBinary($output);
        $this->expectException('App\\Services\\PdfValidationLimit');

        (new PkpPdfValidator($binary))->inspect($path);
    }

    public static function excessiveOutput(): array
    {
        return [
            'stdout' => ["fwrite(STDOUT, str_repeat('x', 65537));"],
            'stderr' => ["fwrite(STDERR, str_repeat('x', 65537));"],
            'combined' => ["fwrite(STDOUT, str_repeat('x', 32768)); fwrite(STDERR, str_repeat('x', 32769));"],
        ];
    }

    public function test_parser_accepts_output_at_the_combined_operation_limit(): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $binary = $this->controlledBinary("fwrite(STDOUT, str_repeat('x', 32768)); fwrite(STDERR, str_repeat('x', 32768));");

        $inspection = (new PkpPdfValidator($binary))->inspect($path);

        $this->assertSame(1, $inspection->pageCount);
    }

    public function test_parser_enforces_the_five_second_operation_timeout(): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $binary = $this->controlledBinary('usleep(6000000);');
        $this->expectException('App\\Services\\PdfValidationLimit');

        (new PkpPdfValidator($binary))->inspect($path);
    }

    public function test_inspection_uses_one_shared_fifteen_second_budget(): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $binary = $this->controlledBinary('', 4000000);
        $this->expectException('App\\Services\\PdfValidationLimit');

        (new PkpPdfValidator($binary))->inspect($path);
    }

    /** @dataProvider supportedVersions */
    public function test_supported_validator_versions_have_no_local_major_assumption(string $version): void
    {
        $path = $this->writePdf($this->validPdfBytes());

        $inspection = (new PkpPdfValidator($this->controlledBinary('', 0, $version)))->inspect($path);

        $this->assertSame($version, $inspection->validatorVersion);
    }

    public static function supportedVersions(): array
    {
        return [['qpdf version 11.9.1'], ['qpdf version 12.4.0'], ['qpdf version 14.0.0']];
    }

    public function test_unsupported_validator_major_is_unavailable(): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $this->expectException(PdfValidatorUnavailable::class);

        (new PkpPdfValidator($this->controlledBinary('', 0, 'qpdf version 10.6.0')))->inspect($path);
    }

    private function controlledBinary(string $check, int $delay = 0, string $version = 'qpdf version 12.4.0'): string
    {
        $binary = $this->temporaryPath();
        $body = '#!'.PHP_BINARY."\n<?php\nusleep(".$delay.");\nswitch (\$argv[1]) {\n"
            .'case "--version": echo '.var_export($version."\n", true)."; break;\n"
            ."case '--requires-password': exit(2);\n"
            ."case '--check': ".$check." break;\n"
            ."case '--show-npages': echo '1'; break;\n}\n";
        file_put_contents($binary, $body);
        chmod($binary, 0700);

        return $binary;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_inspects_real_pdf_structure_and_returns_actual_metadata(): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $validator = new PkpPdfValidator((string) getenv('PKP_QPDF_BINARY'));

        $inspection = $validator->inspect($path);

        $this->assertSame(hash_file('sha256', $path), $inspection->sha256);
        $this->assertSame(filesize($path), $inspection->byteSize);
        $this->assertSame(1, $inspection->pageCount);
        $this->assertSame('application/pdf', $inspection->detectedMime);
        $versionProcess = new Process([(string) getenv('PKP_QPDF_BINARY'), '--version']);
        $versionProcess->mustRun();
        $this->assertSame(strtok(trim($versionProcess->getOutput()), "\r\n"), $inspection->validatorVersion);
        $this->assertSame(1, preg_match('/^qpdf version (\d+)\./', $inspection->validatorVersion, $matches));
        $this->assertGreaterThanOrEqual(11, (int) $matches[1]);
    }

    public function test_rejects_corrupt_empty_zero_page_password_required_and_non_pdf_files(): void
    {
        $validPath = $this->writePdf($this->validPdfBytes());
        $encryptedPath = $this->temporaryPath();
        (new Process([
            (string) getenv('PKP_QPDF_BINARY'),
            $validPath,
            '--encrypt',
            'user-secret',
            'owner-secret',
            '256',
            '--',
            $encryptedPath,
        ]))->mustRun();

        foreach ([
            $this->writePdf('not a PDF'),
            $this->writePdf(''),
            $this->writePdf($this->zeroPagePdf()),
            $encryptedPath,
            $this->writePdf('plain text document'),
        ] as $path) {
            try {
                (new PkpPdfValidator((string) getenv('PKP_QPDF_BINARY')))->inspect($path);
                $this->fail('A corrupt, empty, zero-page, password-required, or non-PDF file must be rejected.');
            } catch (InvalidPdf) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_missing_or_unsupported_qpdf_is_a_runtime_readiness_failure(): void
    {
        $path = $this->writePdf($this->validPdfBytes());

        try {
            (new PkpPdfValidator('/path/that/does/not/exist/qpdf'))->inspect($path);
            $this->fail('A missing validator executable must fail readiness.');
        } catch (PdfValidatorUnavailable) {
            $this->assertTrue(true);
        }
    }

    public function test_validator_metadata_keeps_only_the_bounded_version_line(): void
    {
        $path = $this->writePdf($this->validPdfBytes());
        $binary = $this->temporaryPath();
        file_put_contents($binary, <<<'SH'
#!/bin/sh
case "$1" in
    --version)
        printf 'qpdf version 12.4.0\nRun qpdf --copyright to see copyright and license information.\n'
        ;;
    --requires-password)
        exit 2
        ;;
    --check)
        exit 0
        ;;
    --show-npages)
        printf '1\n'
        ;;
esac
SH);
        chmod($binary, 0700);

        $inspection = (new PkpPdfValidator($binary))->inspect($path);

        $this->assertSame('qpdf version 12.4.0', $inspection->validatorVersion);
        $this->assertLessThanOrEqual(64, strlen($inspection->validatorVersion));
    }

    private function validPdfBytes(): string
    {
        return Pdf::loadHTML('<html><body><h1>PKP test</h1><p>Valid one-page document.</p></body></html>')
            ->setPaper('a4', 'landscape')
            ->output();
    }

    private function writePdf(string $contents): string
    {
        $path = $this->temporaryPath();
        file_put_contents($path, $contents);

        return $path;
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkp-qpdf-');
        if ($path === false) {
            throw new \RuntimeException('Could not create temporary PDF test file.');
        }
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function zeroPagePdf(): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ([
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [] /Count 0 >>',
        ] as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 3\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }
}
