<?php

namespace App\Domain\Admission\Data;

final readonly class CreateApplicationPeriodData
{
    public function __construct(
        public int $academicYearId,
        public int $schoolId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public ?int $maxApplications,
        public int $status,
    ) {}
}
