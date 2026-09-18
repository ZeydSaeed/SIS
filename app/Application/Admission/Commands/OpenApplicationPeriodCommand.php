<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class OpenApplicationPeriodCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public ?int $maxApplications = null,
        public ?string $idempotencyKey = null,
    ) {}
}
