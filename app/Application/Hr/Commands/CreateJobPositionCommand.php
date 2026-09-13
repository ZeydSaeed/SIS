<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;

final readonly class CreateJobPositionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public int $category,
        public ?string $idempotencyKey,
    ) {}
}
