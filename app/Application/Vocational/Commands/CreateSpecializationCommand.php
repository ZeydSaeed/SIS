<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class CreateSpecializationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $idempotencyKey,
        public ?string $description = null,
        public ?string $correlationId = null,
    ) {}
}
