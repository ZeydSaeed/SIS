<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/** Create (examTypeId null) or change one exam type of the school. */
final readonly class SaveExamTypeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $examTypeId,
        public string $code,
        public string $name,
        public int $weightPercentage,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
