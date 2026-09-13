<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class AddSubjectPrerequisiteCommand implements Command
{
    public function __construct(
        public int $subjectId,
        public int $prerequisiteSubjectId,
        public ?string $idempotencyKey,
    ) {}
}
