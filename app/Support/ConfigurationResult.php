<?php

namespace App\Support;

class ConfigurationResult
{
    /**
     * @param  list<string>  $allowedAssessorUuids
     */
    public function __construct(
        public readonly ?string $configurationUuid,
        public readonly int $version,
        public readonly ?string $defaultAssessorUuid,
        public readonly array $allowedAssessorUuids,
        public readonly ?\DateTimeImmutable $deadlineAt = null,
        public readonly ?int $year = null,
        public readonly ?int $month = null,
    ) {}
}
