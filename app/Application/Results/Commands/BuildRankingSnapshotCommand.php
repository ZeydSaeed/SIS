<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

final readonly class BuildRankingSnapshotCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public string $idempotencyKey,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
