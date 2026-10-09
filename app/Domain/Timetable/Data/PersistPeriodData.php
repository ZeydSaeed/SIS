<?php

namespace App\Domain\Timetable\Data;

use App\Domain\Timetable\ValueObjects\PeriodPresentation;

/**
 * One period of the school day («توقيت الحصص»): number, HH:MM start/end, lesson or break, and — when given —
 * its presentation (title, abbreviation, colour, show / print targets). A null presentation leaves the stored
 * one untouched (re-timing a day never resets titles or colours).
 */
final readonly class PersistPeriodData
{
    public function __construct(
        public int $schoolId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public int $periodType,
        public ?PeriodPresentation $presentation = null,
    ) {}
}
