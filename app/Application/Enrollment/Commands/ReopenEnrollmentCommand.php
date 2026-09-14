<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class ReopenEnrollmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public ?string $idempotencyKey,
    ) {}
}
