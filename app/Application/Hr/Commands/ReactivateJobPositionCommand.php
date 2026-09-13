<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateJobPositionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $jobPositionId,
        public ?string $idempotencyKey,
    ) {}
}
