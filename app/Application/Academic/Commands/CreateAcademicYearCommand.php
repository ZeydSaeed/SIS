<?php

namespace App\Application\Academic\Commands;

use App\Application\Contracts\Command;

final readonly class CreateAcademicYearCommand implements Command
{
    public function __construct(
        public string $code,
        public string $name,
        public string $startDate,
        public string $endDate,
        public bool $isCurrent = false,
        public int $status = 1,
        public ?string $idempotencyKey = null,
    ) {}
}
