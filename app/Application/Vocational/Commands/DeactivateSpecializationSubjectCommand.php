<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateSpecializationSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
