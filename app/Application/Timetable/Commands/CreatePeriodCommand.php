<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Domain\Timetable\ValueObjects\PeriodPresentation;

final readonly class CreatePeriodCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public int $periodType,
        public string $idempotencyKey,
        /** Title, abbreviation, colour and show / print targets (null = keep the stored ones / defaults). */
        public ?PeriodPresentation $presentation = null,
    ) {}
}
