<?php

namespace Tests\Unit;

use App\Support\ActivityInput;
use App\Support\ExactValue;
use App\Support\IndicatorInput;
use PHPUnit\Framework\TestCase;

class PkpCalculationContractTest extends TestCase
{
    public function test_exact_value_normalizes_and_displays_repeating_fraction_without_rounding(): void
    {
        $value = new ExactValue('200', '3');

        $this->assertSame('200', $value->numerator);
        $this->assertSame('3', $value->denominator);
        $this->assertSame('66,666666...', $value->display);
        $this->assertTrue($value->repeating);
    }

    public function test_indicator_and_activity_inputs_expose_read_only_contract_fields(): void
    {
        $activity = new ActivityInput('Review files', 'files', '0.125000', '3.0000', '90.0000', '2.0000');
        $indicator = new IndicatorInput('Service quality', [$activity]);

        $this->assertSame('Service quality', $indicator->title);
        $this->assertSame([$activity], $indicator->activities);
        $this->assertSame('files', $activity->unit);
        $this->assertSame('0.125000', $activity->targetCredit);
        $this->assertSame('3.0000', $activity->targetQuantity);
        $this->assertSame('90.0000', $activity->targetQuality);
        $this->assertSame('2.0000', $activity->realizedQuantity);
    }
}
