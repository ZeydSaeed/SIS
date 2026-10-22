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

    #[Test]
    public function exact_half_at_pass_threshold_rounds_up_and_passes(): void
    {
        // Regression: 0.5·36.01 + 0.5·63.98 = 49.995 exactly → 50.00. Float rounding gave 49.99 (fail).
        $result = TermResultCalculator::calculateOfficial([
            new TermGradeContribution(1, 10, 50, '36.01', '100', false, GradeStatus::Finalized->value),
            new TermGradeContribution(2, 11, 50, '63.98', '100', false, GradeStatus::Finalized->value),
        ], passThreshold: '50');

        $this->assertSame('50.00', $result->weightedTotal);
        $this->assertSame(1, $result->passFail);
    }

    #[Test]
    public function absent_component_is_excluded_from_the_exact_total(): void
    {
        $result = TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 50, '33.33', '100', false, GradeStatus::Entered->value),
            new TermGradeContribution(2, 11, 50, null, '100', true, GradeStatus::Entered->value),
        ]);

        // 0.5·33.33 = 16.665 → 16.67; the absent half contributes nothing and marks the result incomplete.
        $this->assertSame('16.67', $result->weightedTotal);
        $this->assertTrue($result->incomplete);
    }

    #[Test]
    public function only_zero_weight_components_scored_gives_no_total(): void
    {
        $result = TermResultCalculator::calculateOperational([
            new TermGradeContribution(1, 10, 100, null, '100', true, GradeStatus::Entered->value),
            new TermGradeContribution(2, 11, 0, '90', '100', false, GradeStatus::Entered->value),
        ], passThreshold: '50');

        $this->assertNull($result->weightedTotal);
        $this->assertNull($result->passFail);
        $this->assertTrue($result->incomplete);
    }
}
