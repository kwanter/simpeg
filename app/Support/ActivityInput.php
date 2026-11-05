<?php

namespace App\Support;

class ActivityInput
{
    public function __construct(
        public readonly ?string $description,
        public readonly ?string $unit,
        public readonly ?string $targetCredit,
        public readonly ?string $targetQuantity,
        public readonly ?string $targetQuality,
        public readonly ?string $realizedQuantity,
    ) {}
}
