<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Ends a division with its groups and memberships (status 2 — never deleted). */
final readonly class EndTimetableDivisionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $divisionId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
