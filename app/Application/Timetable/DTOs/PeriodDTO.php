<?php

namespace App\Application\Timetable\DTOs;

final readonly class PeriodDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public int $periodType,
    ) {}
}
