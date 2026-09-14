<?php

namespace App\Domain\Timetable\Data;

final readonly class PeriodSnapshot
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
