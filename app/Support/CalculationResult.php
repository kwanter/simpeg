<?php

namespace App\Support;

class CalculationResult
{
    /**
     * @param  list<array{title: ?string, activities: list<array<string, mixed>>, score: ExactValue}>  $indicators
     */
    public function __construct(
        public readonly array $indicators,
        public readonly ExactValue $overallScore,
        public readonly string $predicate,
        public readonly string $version = 'pkp-v1',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'overall_score' => $this->exactValueData($this->overallScore),
            'predicate' => $this->predicate,
            'indicators' => array_map(function (array $indicator): array {
                return [
                    'title' => $indicator['title'],
                    'score' => $this->exactValueData($indicator['score']),
                    'activities' => array_map(fn (array $activity): array => [
                        'description' => $activity['description'],
                        'unit' => $activity['unit'],
                        'target_credit' => $this->exactValueData($activity['targetCredit']),
                        'target_quantity' => $this->exactValueData($activity['targetQuantity']),
                        'target_quality' => $this->exactValueData($activity['targetQuality']),
                        'realized_quantity' => $this->exactValueData($activity['realizedQuantity']),
                        'realized_credit' => $this->exactValueData($activity['realizedCredit']),
                        'realized_quality' => $this->exactValueData($activity['realizedQuality']),
                        'achievement' => $this->exactValueData($activity['achievement']),
                    ], $indicator['activities']),
                ];
            }, $this->indicators),
        ];
    }

    /**
     * @return array{numerator: string, denominator: string, display: string, repeating: bool}
     */
    private function exactValueData(ExactValue $value): array
    {
        return [
            'numerator' => $value->numerator,
            'denominator' => $value->denominator,
            'display' => $value->display,
            'repeating' => $value->repeating,
        ];
    }
}
