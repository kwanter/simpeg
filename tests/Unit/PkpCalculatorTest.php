<?php

namespace Tests\Unit;

use App\Services\PkpCalculator;
use App\Support\ActivityInput;
use App\Support\IndicatorInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PkpCalculatorTest extends TestCase
{
    public function test_activity_score_is_capped_but_realized_credit_uses_actual_quantity(): void
    {
        $result = (new PkpCalculator)->calculate([
            new IndicatorInput('Output', [
                $this->activity('5.0000', '6.0000', '0.020000'),
                $this->activity('5.0000', '3.0000', '0.020000'),
            ]),
        ]);

        $this->assertSame('100', $result->indicators[0]['activities'][0]['achievement']->display);
        $this->assertSame('0,12', $result->indicators[0]['activities'][0]['realizedCredit']->display);
        $this->assertSame('60', $result->indicators[0]['activities'][1]['achievement']->display);
        $this->assertSame('80', $result->overallScore->display);
    }

    public function test_all_activities_contribute_and_indicators_are_equally_weighted(): void
    {
        $result = (new PkpCalculator)->calculate([
            new IndicatorInput('Larger group', [
                $this->activity('1.0000', '1.0000'),
                $this->activity('1.0000', '1.0000'),
                $this->activity('5.0000', '4.0000'),
            ]),
            new IndicatorInput('Smaller group', [
                $this->activity('15000.0000', '13000.0000'),
            ]),
        ]);

        $this->assertSame('93,333333...', $result->indicators[0]['score']->display);
        $this->assertSame('86,666666...', $result->indicators[1]['score']->display);
        $this->assertSame('90', $result->overallScore->display);
    }

    public function test_raw_unrounded_score_selects_the_confirmed_rating_thresholds(): void
    {
        $atBaik = (new PkpCalculator)->calculate([
            new IndicatorInput('Rating', [$this->activity('10000.0000', '9099.0000')]),
        ]);
        $aboveBaik = (new PkpCalculator)->calculate([
            new IndicatorInput('Rating', [$this->activity('1000.0000', '909.9100')]),
        ]);

        $this->assertSame('90,99', $atBaik->overallScore->display);
        $this->assertSame('Baik', $atBaik->predicate);
        $this->assertSame('90,991', $aboveBaik->overallScore->display);
        $this->assertSame('Sangat Baik', $aboveBaik->predicate);
    }

    public function test_calculator_rejects_zero_target_and_empty_indicator_groups(): void
    {
        try {
            (new PkpCalculator)->calculate([
                new IndicatorInput('Invalid', [$this->activity('0.0000', '0.0000')]),
            ]);
            $this->fail('A zero target cannot produce an achievement score.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        try {
            (new PkpCalculator)->calculate([new IndicatorInput('Empty', [])]);
            $this->fail('An indicator without activities cannot produce a score.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    private function activity(string $target, string $realized, string $credit = '1.000000'): ActivityInput
    {
        return new ActivityInput('Task', 'unit', $credit, $target, '75.0000', $realized);
    }
}
