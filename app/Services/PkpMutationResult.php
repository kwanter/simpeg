<?php

namespace App\Services;

class PkpMutationResult
{
    public function __construct(
        public readonly string $recordUuid,
        public readonly int $version,
        public readonly ?string $officialRevisionUuid = null,
        public readonly ?string $pendingRevisionUuid = null,
        public readonly ?string $submissionUuid = null,
        public readonly ?string $documentUuid = null,
        public readonly string $completeness = 'incomplete',
        public readonly ?string $lateness = null,
    ) {}
}
