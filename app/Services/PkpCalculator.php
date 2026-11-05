<?php

namespace App\Services;

use App\Support\ActivityInput;
use App\Support\CalculationResult;
use App\Support\ExactValue;
use App\Support\IndicatorInput;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use InvalidArgumentException;

class PkpCalculator
{
    /**
     * @param  list<IndicatorInput>  $indicators
     */
    public function calculate(array $indicators): CalculationResult
    {
        if ($indicators === [] || count($indicators) > 50) {
            throw new InvalidArgumentException('A complete calculation requires 1 to 50 indicators.');
        }

        $activityCount = 0;
        $indicatorResults = [];
        $indicatorScores = [];

        foreach ($indicators as $indicator) {
            if (! $indicator instanceof IndicatorInput || trim((string) $indicator->title) === '' || $indicator->activities === []) {
                throw new InvalidArgumentException('Every calculated indicator requires a title and at least one activity.');
            }

            $activityResults = [];
            $activityScores = [];
            foreach ($indicator->activities as $activity) {
                if (! $activity instanceof ActivityInput) {
                    throw new InvalidArgumentException('Every activity must use the ActivityInput contract.');
                }

                $activityCount++;
                $result = $this->calculateActivity($activity);
                $activityResults[] = $result;
                $activityScores[] = BigRational::nd($result['achievement']->numerator, $result['achievement']->denominator);
            }

            $indicatorScore = $this->average($activityScores);
            $indicatorScores[] = $indicatorScore;
            $indicatorResults[] = [
                'title' => $indicator->title,
                'activities' => $activityResults,
                'score' => $this->exactValue($indicatorScore),
            ];
        }

        if ($activityCount > 500) {
            throw new InvalidArgumentException('A complete calculation may contain at most 500 activities.');
        }

        $overallScore = $this->average($indicatorScores);

        return new CalculationResult(
            $indicatorResults,
            $this->exactValue($overallScore),
            $this->predicate($overallScore)
        );
    }

    private function calculateActivity(ActivityInput $activity): array
    {
        if (trim((string) $activity->description) === '' || trim((string) $activity->unit) === '') {
            throw new InvalidArgumentException('Every calculated activity requires a description and shared unit.');
        }

        $targetCredit = $this->decimal($activity->targetCredit, 6, '99999999.999999');
        $targetQuantity = $this->decimal($activity->targetQuantity, 4, '9999999999.9999');
        $targetQuality = $this->decimal($activity->targetQuality, 4, '100.0000');
        $realizedQuantity = $this->decimal($activity->realizedQuantity, 4, '9999999999.9999');

        if ($targetQuantity->isZero() || $targetCredit->isNegative() || $targetQuality->isNegative() || $realizedQuantity->isNegative()) {
            throw new InvalidArgumentException('Targets must be positive and realizations and credit must be nonnegative.');
        }

        $achievement = $realizedQuantity->dividedBy($targetQuantity)->multipliedBy(100);
        if ($achievement->compareTo(100) > 0) {
            $achievement = BigRational::of(100);
        }

        $realizedCredit = $realizedQuantity->multipliedBy($targetCredit);

        return [
            'description' => $activity->description,
            'unit' => $activity->unit,
            'targetCredit' => $this->exactValue($targetCredit),
            'targetQuantity' => $this->exactValue($targetQuantity),
            'targetQuality' => $this->exactValue($targetQuality),
            'realizedQuantity' => $this->exactValue($realizedQuantity),
            'realizedCredit' => $this->exactValue($realizedCredit),
            'realizedQuality' => $this->exactValue($achievement),
            'achievement' => $this->exactValue($achievement),
        ];
    }

    private function decimal(?string $value, int $scale, string $maximum): BigRational
    {
        if ($value === null || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Numeric inputs must be invariant unsigned decimal strings.');
        }

        $fraction = $matches[2] ?? '';
        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException('Numeric input exceeds its permitted decimal scale.');
        }

        $digits = BigInteger::of(($matches[1] ?? '0').$fraction);
        $valueRational = BigRational::nd($digits, BigInteger::ten()->power(strlen($fraction)))->simplified();
        if ($valueRational->compareTo($maximum) > 0) {
            throw new InvalidArgumentException('Numeric input exceeds its permitted range.');
        }

        return $valueRational;
    }

    /**
     * @param  list<BigRational>  $values
     */
    private function average(array $values): BigRational
    {
        if ($values === []) {
            throw new InvalidArgumentException('An average requires at least one value.');
        }

        $sum = BigRational::zero();
        foreach ($values as $value) {
            $sum = $sum->plus($value);
        }

        return $sum->dividedBy(count($values))->simplified();
    }

    private function exactValue(BigRational $value): ExactValue
    {
        $value = $value->simplified();

        return new ExactValue((string) $value->getNumerator(), (string) $value->getDenominator());
    }

    private function predicate(BigRational $score): string
    {
        return match (true) {
            $score->compareTo(50) <= 0 => 'Buruk',
            $score->compareTo(60) <= 0 => 'Sedang',
            $score->compareTo(75) <= 0 => 'Cukup',
            $score->compareTo('90.99') <= 0 => 'Baik',
            default => 'Sangat Baik',
        };
    }
}
