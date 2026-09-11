<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class CreateExamSessionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examId,
        public int $subjectId,
        public string $sessionDate,
        public string $startTime,
        public string $endTime,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?int $roomId = null,
        public int $maxGrade = 100,
        public int $passGrade = 50,
        public ?string $correlationId = null,
    ) {}
}
