<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «إعدادات الجدول»: working days, cycle weeks, daily limits, double changeover, soft weights. */
final readonly class SaveTimetableSettingsCommand implements Command
{
    /**
     * @param  list<int>  $days
     * @param  array<int, int>  $weights  priority → weight
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $days,
        public int $cycleWeeks,
        public int $maxTeacherPerDay,
        public int $maxSubjectPerDay,
        public int $doubleChangeoverMinutes,
        public array $weights,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
