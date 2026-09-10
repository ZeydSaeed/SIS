<?php

namespace Tests\Unit\Domain\Exams;

use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\Exceptions\InvalidGradeScoreException;
use App\Domain\Exams\Services\StudentGradeRules;
use App\Domain\Exams\ValueObjects\GradeStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StudentGradeRulesTest extends TestCase
{
    #[Test]
    public function absent_requires_null_score(): void
    {
        StudentGradeRules::assertScoreSemantics(true, null, '100');
        $this->expectException(InvalidGradeScoreException::class);
        StudentGradeRules::assertScoreSemantics(true, '10', '100');
    }

    #[Test]
    public function non_absent_score_must_be_within_max(): void
    {
        StudentGradeRules::assertScoreSemantics(false, '85', '100');
        $this->expectException(InvalidGradeScoreException::class);
        StudentGradeRules::assertScoreSemantics(false, '101', '100');
    }

    #[Test]
    public function finalize_rejects_finalized_and_voided(): void
    {
        StudentGradeRules::assertCanFinalize(GradeStatus::Entered);
        $this->expectException(InvalidGradeCorrectionException::class);
        StudentGradeRules::assertCanFinalize(GradeStatus::Finalized);
    }

    #[Test]
    public function void_requires_current_non_voided(): void
    {
        StudentGradeRules::assertCanVoid(true, GradeStatus::Entered);
        $this->expectException(InvalidGradeCorrectionException::class);
        StudentGradeRules::assertCanVoid(false, GradeStatus::Entered);
    }

    #[Test]
    public function correction_cycle_detection(): void
    {
        StudentGradeRules::assertNoCycle([10, 9, 8], 7);
        $this->expectException(InvalidGradeCorrectionException::class);
        StudentGradeRules::assertNoCycle([10, 9, 8], 9);
    }
}
