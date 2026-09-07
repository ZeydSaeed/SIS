<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class CancelEnrollmentCommand implements Command
{
    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
        public string $effectiveTo,
        public ?int $cancelledBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
