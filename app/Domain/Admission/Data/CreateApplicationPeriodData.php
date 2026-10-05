<?php

namespace App\Domain\Admission\Data;

final readonly class CreateApplicationPeriodData
{
    public function __construct(
        public int $academicYearId,
        public int $schoolId,
        public string $name,
        public string $startDate,
        /** Null = open-ended period. */
        public ?string $endDate,
        public ?int $maxApplications,
        public int $status,
        /** The directorate whose schools file applications in this period (fixed at creation). */
        public ?int $directorateId = null,
    ) {}
}
