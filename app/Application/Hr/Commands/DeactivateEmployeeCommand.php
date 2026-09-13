<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateEmployeeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $employeeId,
        public ?string $idempotencyKey,
    ) {}
}
