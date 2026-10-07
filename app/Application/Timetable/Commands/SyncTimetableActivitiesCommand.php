<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «من المنهج»: turns each requirement (teaching assignment × curriculum hours) not yet covered by an activity
 * into an editable activity — theory singles, practical doubles. Never invents subjects (spec §39).
 */
final readonly class SyncTimetableActivitiesCommand implements Command
{
    /** @param  list<int>|null  $sectionIds  null = every section */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?array $sectionIds,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
