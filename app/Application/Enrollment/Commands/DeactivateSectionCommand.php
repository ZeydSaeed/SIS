<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateSectionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
