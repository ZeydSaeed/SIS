<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateExamEnrollmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examEnrollmentId,
        public int $examSessionId,
        public int $enrollmentId,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?int $status = null,
        public bool $seatNumberProvided = false,
        public ?string $seatNumber = null,
        public ?string $correlationId = null,
    ) {}
}
