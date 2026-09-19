<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class ChangeApplicationPeriodStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $periodId,
        public int $status,
        public ?string $idempotencyKey = null,
    ) {}
}
