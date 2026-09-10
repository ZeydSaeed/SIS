<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class VoidStudentGradeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $gradeId,
        public int $academicYearId,
        public string $reason,
        public ?int $voidedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
