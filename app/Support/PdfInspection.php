<?php

namespace App\Support;

use InvalidArgumentException;

final class PdfInspection
{
    public function __construct(
        public readonly string $sha256,
        public readonly int $byteSize,
        public readonly int $pageCount,
        public readonly string $detectedMime,
        public readonly string $validatorVersion,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new InvalidArgumentException('The inspected PDF hash must be a lowercase SHA-256 value.');
        }
        if ($byteSize < 1) {
            throw new InvalidArgumentException('An inspected PDF must contain at least one byte.');
        }
        if ($pageCount < 1) {
            throw new InvalidArgumentException('An inspected PDF must contain at least one page.');
        }
        if ($detectedMime !== 'application/pdf') {
            throw new InvalidArgumentException('An inspected document must have application/pdf MIME type.');
        }
        if (trim($validatorVersion) === '') {
            throw new InvalidArgumentException('The PDF validator version is required.');
        }
    }
}
