<?php

namespace App\Application\Bank\Commands;

use App\Application\Contracts\Command;

/** Retires a question with a written reason (never deleted, DEC-052); final. */
final readonly class RetireQuestionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $questionId,
        public string $reason,
        public int $actorId,
        public ?string $idempotencyKey,
    ) {}
}
