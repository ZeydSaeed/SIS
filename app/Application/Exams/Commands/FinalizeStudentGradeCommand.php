<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class FinalizeStudentGradeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $gradeId,
        public int $academicYearId,
        public ?int $finalizedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
