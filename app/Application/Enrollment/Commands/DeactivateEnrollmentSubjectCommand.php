<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateEnrollmentSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
        public ?string $idempotencyKey,
    ) {}
}
