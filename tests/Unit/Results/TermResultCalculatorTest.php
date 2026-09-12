<?php

namespace Tests\Unit\Results;

use App\Domain\Exams\ValueObjects\GradeStatus;
use App\Domain\Results\Data\TermGradeContribution;
use App\Domain\Results\Exceptions\TermResultNoGradesException;
use App\Domain\Results\Exceptions\TermResultWeightException;
use App\Domain\Results\Services\TermResultCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TermResultCalculatorTest extends TestCase
{
    #[Test]
    public function calculates_weighted_total_for_single_full_weight_type(): void
    {
        $result = TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 100, '80', '100', false, GradeStatus::Entered->value),
        ], passThreshold: '50');

        $this->assertSame('80.00', $result->weightedTotal);
        $this->assertSame(1, $result->passFail);
        $this->assertFalse($result->incomplete);
    }

    #[Test]
    public function fails_when_weights_do_not_sum_to_100(): void
    {
        $this->expectException(TermResultWeightException::class);

        TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 40, '80', '100', false, GradeStatus::Entered->value),
        ]);
    }

    #[Test]
    public function fails_when_no_eligible_grades(): void
    {
        $this->expectException(TermResultNoGradesException::class);

        TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 100, '80', '100', false, GradeStatus::Voided->value),
        ]);
    }

    #[Test]
    public function absent_marks_incomplete_and_null_total_when_only_absent(): void
    {
        $result = TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 100, null, '100', true, GradeStatus::Entered->value),
        ]);

        $this->assertNull($result->weightedTotal);
        $this->assertTrue($result->incomplete);
    }

    #[Test]
    public function blends_two_types_to_one_hundred(): void
    {
        $result = TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 40, '50', '100', false, GradeStatus::Finalized->value),
            new TermGradeContribution(2, 11, 60, '100', '100', false, GradeStatus::Entered->value),
        ]);

        // 0.4*50 + 0.6*100 = 20 + 60 = 80
        $this->assertSame('80.00', $result->weightedTotal);
        $this->assertFalse($result->incomplete);
    }
}
