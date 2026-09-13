<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class AssignEnrollmentSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $subjectId,
        public bool $isElective,
        public ?string $idempotencyKey,
    ) {}
}
