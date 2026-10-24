<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/** Open a review (objection) of one official grade. The grade itself is not touched. */
final readonly class OpenGradeReviewCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $gradeId,
        public int $academicYearId,
        public string $reason,
        public ?int $requestedBy,
        public ?string $correlationId,
        public ?string $idempotencyKey,
    ) {}
}
