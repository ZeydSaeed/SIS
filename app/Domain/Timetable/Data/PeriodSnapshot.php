<?php

namespace App\Domain\Timetable\Data;

use App\Domain\Timetable\ValueObjects\PeriodPresentation;

final readonly class PeriodSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public int $periodType,
        /** Title, abbreviation, colour, where it shows / prints (defaults: no title, everywhere). */
        public PeriodPresentation $presentation = new PeriodPresentation,
    ) {}
}
