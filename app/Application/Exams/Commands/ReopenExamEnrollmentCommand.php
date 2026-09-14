<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class ReopenExamEnrollmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examEnrollmentId,
        public int $actorUserId,
        public ?string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
