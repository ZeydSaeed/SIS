<?php

namespace App\Domain\Results\Data;

/**
 * One grade contribution toward a term-subject weighted total.
 */
final readonly class TermGradeContribution
{
    public function __construct(
        public int $gradeId,
        public int $examTypeId,
        public int $weightPercentage,
        public ?string $score,
        public string $maxScore,
        public bool $isAbsent,
        public int $status,
    ) {}
}
