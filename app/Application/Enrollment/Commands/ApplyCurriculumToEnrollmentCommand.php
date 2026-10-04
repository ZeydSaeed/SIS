<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

/**
 * Assign the required subjects of the curriculum governing an enrollment.
 * Naturally idempotent: subjects already linked (any status) are left untouched.
 */
final readonly class ApplyCurriculumToEnrollmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public ?string $idempotencyKey = null,
    ) {}
}
