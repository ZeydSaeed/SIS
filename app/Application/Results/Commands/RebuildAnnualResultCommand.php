<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

final readonly class RebuildAnnualResultCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public string $mode,
        public string $idempotencyKey,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
