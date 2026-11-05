<?php

namespace App\Support;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use InvalidArgumentException;

class ExactValue
{
    public readonly string $numerator;

    public readonly string $denominator;

    public readonly string $display;

    public readonly bool $repeating;

    public function __construct(string $numerator, string $denominator)
    {
        if (BigInteger::of($denominator)->isZero()) {
            throw new InvalidArgumentException('An exact value denominator must not be zero.');
        }

        $value = BigRational::nd($numerator, $denominator)->simplified();
        $this->numerator = (string) $value->getNumerator();
        $this->denominator = (string) $value->getDenominator();
        $this->repeating = ! $this->hasTerminatingDecimal($value->getDenominator());
        $this->display = $this->formatIndonesianDecimal($value, $this->repeating);
    }

    private function hasTerminatingDecimal(BigInteger $denominator): bool
    {
        foreach ([2, 5] as $factor) {
            while ($denominator->remainder($factor)->isZero()) {
                $denominator = $denominator->quotient($factor);
            }
        }

        return $denominator->compareTo(1) === 0;
    }

    private function formatIndonesianDecimal(BigRational $value, bool $repeating): string
    {
        $numerator = $value->getNumerator();
        $negative = $numerator->isNegative();
        [$whole, $remainder] = $numerator->abs()->quotientAndRemainder($value->getDenominator());
        $prefix = $negative ? '-' : '';

        if ($remainder->isZero()) {
            return $prefix.(string) $whole;
        }

        $digits = '';
        while (! $remainder->isZero() && ($repeating ? strlen($digits) < 6 : true)) {
            [$digit, $remainder] = $remainder->multipliedBy(10)->quotientAndRemainder($value->getDenominator());
            $digits .= (string) $digit;
        }

        if ($repeating) {
            return $prefix.(string) $whole.','.$digits.'...';
        }

        return $prefix.(string) $whole.','.rtrim($digits, '0');
    }
}
