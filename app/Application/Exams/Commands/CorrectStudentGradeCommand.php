<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class CorrectStudentGradeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $gradeId,
        public int $academicYearId,
        public ?string $score,
        public bool $isAbsent,
        public string $reason,
        public ?int $correctedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
