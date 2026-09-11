<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class CancelExamCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examId,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
