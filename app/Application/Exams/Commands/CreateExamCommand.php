<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class CreateExamCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $examTypeId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
