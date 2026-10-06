<?php

namespace App\Domain\Timetable\Data;

/** One period of the school day («توقيت الحصص»): number, HH:MM start/end, lesson or break. */
final readonly class PersistPeriodData
{
    public function __construct(
        public int $schoolId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public int $periodType,
    ) {}
}
