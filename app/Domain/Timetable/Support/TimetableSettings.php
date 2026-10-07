<?php

namespace App\Domain\Timetable\Support;

use App\Domain\Timetable\ValueObjects\ConstraintPriority;

/**
 * «إعدادات الجدول» of one school-year (timetable.configs). Without a row the school runs on the
 * {@see SchoolWeek} defaults — the behaviour the builder always had.
 */
final readonly class TimetableSettings
{
    /**
     * @param  list<int>  $days  working days (`day_of_week` 1–7) in week order
     * @param  array<int, int>  $weights  soft-constraint weight per {@see ConstraintPriority} value
     */
    public function __construct(
        public array $days = SchoolWeek::DAYS,
        public int $cycleWeeks = 1,
        public int $maxTeacherPerDay = SchoolWeek::MAX_TEACHER_LESSONS_PER_DAY,
        public int $maxSubjectPerDay = SchoolWeek::MAX_SUBJECT_LESSONS_PER_DAY,
        public int $doubleChangeoverMinutes = SchoolWeek::MAX_DOUBLE_BREAK_MINUTES,
        public array $weights = [],
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    /** Weeks of the cycle a lesson occupies: one week, or all of them when it runs every week. */
    public function weeksOf(?int $weekNo): array
    {
        return $weekNo === null ? range(1, $this->cycleWeeks) : [$weekNo];
    }

    public function weight(ConstraintPriority $priority): int
    {
        return $this->weights[$priority->value] ?? $priority->defaultWeight();
    }

    /** Lessons of one teacher the cycle can hold: days × daily limit, at most the slots. */
    public function teacherCapacity(int $lessonPeriods): int
    {
        return min(count($this->days) * $lessonPeriods, count($this->days) * $this->maxTeacherPerDay);
    }

    /**
     * @return array{working_days: list<int>, cycle_weeks: int, max_teacher_per_day: int, max_subject_per_day: int, double_changeover_minutes: int, weights: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'working_days' => $this->days,
            'cycle_weeks' => $this->cycleWeeks,
            'max_teacher_per_day' => $this->maxTeacherPerDay,
            'max_subject_per_day' => $this->maxSubjectPerDay,
            'double_changeover_minutes' => $this->doubleChangeoverMinutes,
            'weights' => $this->weights,
        ];
    }
}
