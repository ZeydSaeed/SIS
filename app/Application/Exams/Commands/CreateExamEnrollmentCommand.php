<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class CreateExamEnrollmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examSessionId,
        public int $enrollmentId,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $seatNumber = null,
        public ?string $correlationId = null,
    ) {}
}
