<?php

namespace App\Support;

class IndicatorInput
{
    /**
     * @param  list<ActivityInput>  $activities
     */
    public function __construct(
        public readonly ?string $title,
        public readonly array $activities,
    ) {}
}
