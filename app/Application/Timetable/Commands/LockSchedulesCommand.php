<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «تثبيت / إلغاء التثبيت»: a locked lesson is never moved by the generator or by hand until unlocked. */
final readonly class LockSchedulesCommand implements Command
{
    /** @param  list<int>  $scheduleIds */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $scheduleIds,
        public bool $lock,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
