<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

final readonly class FinalizeAnnualResultCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public string $idempotencyKey,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
