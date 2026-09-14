<?php

namespace App\Application\Academic\DTOs;

final readonly class HolidayDTO
{
    public function __construct(
        public int $id,
        public int $academicYearId,
        public ?int $schoolId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public int $holidayType,
        public string $createdAt,
    ) {}
}
