<?php

namespace App\Services;

use App\Support\PdfInspection;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

class PkpPdfValidator
{
    private const OPERATION_SECONDS = 5;

    private const TOTAL_SECONDS = 15;

    private const OUTPUT_BYTES = 64 * 1024;

    private ?string $version = null;

    public function __construct(private readonly ?string $binary = null) {}

    public function inspect(string $privateStagedPath): PdfInspection
    {
        $deadline = hrtime(true) + self::TOTAL_SECONDS * 1_000_000_000;
        $binary = $this->binary ?? config('pkp.qpdf_binary');
        if (! is_string($binary) || ! str_starts_with($binary, '/') || ! is_executable($binary)) {
            throw new PdfValidatorUnavailable('The configured qpdf executable is unavailable.');
        }

        $realPath = realpath($privateStagedPath);
        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new InvalidPdf('The staged upload is not a readable file.');
        }

        $byteSize = filesize($realPath);
        if ($byteSize === false || $byteSize < 1 || $byteSize > 10 * 1024 * 1024) {
            throw new InvalidPdf('The PDF file size is outside the permitted range.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        if ($fileInfo->file($realPath) !== 'application/pdf') {
            throw new InvalidPdf('The uploaded file is not a PDF document.');
        }

        $version = $this->resolveVersion($binary, $deadline);
        $passwordStatus = $this->run($binary, ['--requires-password', $realPath], $deadline);
        if ($passwordStatus['exit_code'] === 0) {
            throw new InvalidPdf('The PDF requires a password to read.');
        }
        if (! in_array($passwordStatus['exit_code'], [2, 3], true)) {
            throw new InvalidPdf('The PDF encryption status could not be established.');
        }

        $structure = $this->run($binary, ['--check', $realPath], $deadline);
        if ($structure['exit_code'] !== 0) {
            throw new InvalidPdf('The PDF structure is corrupt or unreadable.');
        }

        $pageCountProcess = $this->run($binary, ['--show-npages', $realPath], $deadline);
        $pageCountText = trim($pageCountProcess['output']);
        if ($pageCountProcess['exit_code'] !== 0 || preg_match('/^[0-9]+$/', $pageCountText) !== 1) {
            throw new InvalidPdf('The PDF page count could not be read.');
        }
        $pageCount = (int) $pageCountText;
        if ($pageCount < 1) {
            throw new InvalidPdf('The PDF must contain at least one page.');
        }

        $sha256 = hash_file('sha256', $realPath);
        if (! is_string($sha256)) {
            throw new PdfValidatorUnavailable('The staged PDF could not be hashed.');
        }

        return new PdfInspection($sha256, $byteSize, $pageCount, 'application/pdf', $version);
    }

    private function resolveVersion(string $binary, int $deadline): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        $process = $this->run($binary, ['--version'], $deadline);
        $versionOutput = trim($process['output']);
        $version = trim(strtok($versionOutput, "\r\n") ?: '');
        if ($process['exit_code'] !== 0 || preg_match('/^qpdf version (\d+)\./', $version, $matches) !== 1 || (int) $matches[1] < 11) {
            throw new PdfValidatorUnavailable('qpdf version 11 or newer is required.');
        }

        return $this->version = $version;
    }

    /**
     * @param  list<string>  $arguments
     * @return array{exit_code: ?int, output: string}
     */
    private function run(string $binary, array $arguments, int $deadline): array
    {
        $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
        if ($remaining <= 0) {
            throw new PdfValidationLimit('PDF inspection exceeded its total time limit.');
        }
        $process = new Process(array_merge([$binary], $arguments));
        $process->setTimeout(min(self::OPERATION_SECONDS, $remaining));
        $process->disableOutput();
        $bytes = 0;
        $output = '';
        $exceeded = false;

        try {
            $process->run(function (string $type, string $buffer) use (&$bytes, &$output, &$exceeded): void {
                if ($exceeded) {
                    return;
                }
                $bytes += strlen($buffer);
                if ($bytes > self::OUTPUT_BYTES) {
                    $exceeded = true;
                    throw new PdfValidationLimit('PDF inspection exceeded its output limit.');
                }
                if ($type === Process::OUT) {
                    $output .= $buffer;
                }
            });
        } catch (ProcessTimedOutException $exception) {
            throw new PdfValidationLimit('PDF inspection exceeded its time limit.', previous: $exception);
        } catch (ProcessRuntimeException $exception) {
            throw new PdfValidatorUnavailable('qpdf could not start or complete PDF inspection.', previous: $exception);
        } finally {
            $process->stop(0);
        }
        if (hrtime(true) > $deadline) {
            throw new PdfValidationLimit('PDF inspection exceeded its total time limit.');
        }

        return ['exit_code' => $process->getExitCode(), 'output' => $output];
    }
}

class InvalidPdf extends \RuntimeException {}

class PdfValidatorUnavailable extends \RuntimeException {}
