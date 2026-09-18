<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class ConvertApplicationToStudentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public ?int $reviewedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
