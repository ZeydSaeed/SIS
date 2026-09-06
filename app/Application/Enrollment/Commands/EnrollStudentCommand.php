<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class EnrollStudentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $studentId,
        public int $classId,
        public int $sectionId,
        public string $effectiveFrom,
        public ?int $specializationId = null,
        public ?int $enrolledBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
