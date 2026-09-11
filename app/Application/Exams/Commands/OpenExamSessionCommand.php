<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class OpenExamSessionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examSessionId,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
