<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateSubjectCommand implements Command
{
    public function __construct(
        public int $subjectId,
        public ?string $idempotencyKey,
    ) {}
}
