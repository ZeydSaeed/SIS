<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateExamSessionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examSessionId,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $sessionDate = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?int $roomId = null,
        public bool $roomIdProvided = false,
        public ?int $maxGrade = null,
        public ?int $passGrade = null,
        public ?string $correlationId = null,
    ) {}
}
