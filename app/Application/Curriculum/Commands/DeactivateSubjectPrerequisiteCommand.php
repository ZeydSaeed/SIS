<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateSubjectPrerequisiteCommand implements Command
{
    public function __construct(
        public int $prerequisiteId,
        public ?string $idempotencyKey,
    ) {}
}
