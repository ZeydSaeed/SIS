<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Ends a constraint rule (status 2 + effective_to); it stays in the history. */
final readonly class EndTimetableRuleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $ruleId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
