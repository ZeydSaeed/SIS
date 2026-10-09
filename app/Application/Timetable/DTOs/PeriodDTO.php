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
        public ?string $name = null,
        public ?string $abbreviation = null,
        public ?int $colorHue = null,
        public int $showIn = 31,
        public int $printIn = 31,
    ) {}
}
