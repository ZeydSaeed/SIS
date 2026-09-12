<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

final readonly class CalculateTermResultCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
        public string $idempotencyKey,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
