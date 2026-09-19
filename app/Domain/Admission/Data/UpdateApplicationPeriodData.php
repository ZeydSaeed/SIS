<?php

namespace App\Domain\Admission\Data;

final readonly class UpdateApplicationPeriodData
{
    public function __construct(
        public int $periodId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public ?int $maxApplications,
    ) {}
}
