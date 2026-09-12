<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class AssignTeacherSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $subjectId,
        public int $academicYearId,
        public ?string $idempotencyKey,
    ) {}
}
