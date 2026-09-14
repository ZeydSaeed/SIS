<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateClassCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $classId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
