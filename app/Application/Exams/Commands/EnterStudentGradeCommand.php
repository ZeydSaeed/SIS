<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class EnterStudentGradeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examEnrollmentId,
        public ?string $score,
        public bool $isAbsent,
        public ?int $enteredBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
