<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateEnrollmentPlacementCommand implements Command
{
    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
        public int $classId,
        public int $sectionId,
        public ?int $specializationId = null,
        public ?int $updatedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
