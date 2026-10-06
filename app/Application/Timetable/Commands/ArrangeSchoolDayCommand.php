<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «توزيع الاستراحات تلقائياً»: re-times the school day (same lessons, breaks of 5 / 10 / 15 minutes). */
final readonly class ArrangeSchoolDayCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $startTime,
        public int $lessonMinutes,
        public string $idempotencyKey,
    ) {}
}
