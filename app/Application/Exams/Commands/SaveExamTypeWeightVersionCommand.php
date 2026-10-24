<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/** A new weight version of an exam type for one academic year; stored results are not recalculated. */
final readonly class SaveExamTypeWeightVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examTypeId,
        public int $academicYearId,
        public int $weightPercentage,
        public string $reason,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
