<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateSpecializationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $specializationId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
