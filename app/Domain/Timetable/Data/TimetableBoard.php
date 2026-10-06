<?php

namespace App\Domain\Timetable\Data;

use App\Domain\Timetable\Services\PeriodTimeGuard;
use App\Domain\Timetable\Support\SchoolWeek;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * The school-year timetable as plain data, for the pure builder services (audit, auto-place, shift).
 *
 * - periods: the school day in order (lessons and breaks);
 * - schedules: active lessons on the grid;
 * - requirements: per section, a subject taught by a teacher `weekly` times a week (null = not set);
 * - teacherSubjects: "teacher:subject" keys the teacher may teach (teacher_subjects);
 * - activeTeacherIds: teachers active in the school this year;
 * - practicalSubjectIds: subjects of type «عملي» (taught as consecutive doubles).
 */
final readonly class TimetableBoard
{
    /** @var list<int> lesson period ids in day order */
    public array $lessonPeriodIds;

    /**
     * @param  list<PeriodSnapshot>  $periods
     * @param  list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int}>  $schedules
     * @param  list<array{section_id: int, subject_id: int, teacher_id: int, weekly: int|null}>  $requirements
     * @param  array<string, true>  $teacherSubjects
     * @param  list<int>  $activeTeacherIds
     * @param  list<int>  $practicalSubjectIds
     */
    public function __construct(
        public array $periods,
        public array $schedules,
        public array $requirements,
        public array $teacherSubjects,
        public array $activeTeacherIds,
        public array $practicalSubjectIds,
    ) {
        $ordered = $periods;
        usort($ordered, static fn (PeriodSnapshot $a, PeriodSnapshot $b): int => $a->periodNumber <=> $b->periodNumber);
        $this->lessonPeriodIds = array_values(array_map(
            static fn (PeriodSnapshot $p): int => $p->id,
            array_filter($ordered, static fn (PeriodSnapshot $p): bool => $p->periodType === PeriodType::Lesson->value),
        ));
    }

    /**
     * True when the second lesson follows the first with at most a short changeover
     * ({@see SchoolWeek::MAX_DOUBLE_BREAK_MINUTES}) — a practical double. The main break splits it.
     */
    public function adjacent(int $firstPeriodId, int $secondPeriodId): bool
    {
        $index = array_search($firstPeriodId, $this->lessonPeriodIds, true);
        if ($index === false || ($this->lessonPeriodIds[$index + 1] ?? null) !== $secondPeriodId) {
            return false;
        }

        $byId = [];
        foreach ($this->periods as $period) {
            $byId[$period->id] = $period;
        }
        $end = PeriodTimeGuard::minutes($byId[$firstPeriodId]->endTime);
        $start = PeriodTimeGuard::minutes($byId[$secondPeriodId]->startTime);

        return $end !== null && $start !== null && $start - $end <= SchoolWeek::MAX_DOUBLE_BREAK_MINUTES;
    }

    public function isPractical(int $subjectId): bool
    {
        return in_array($subjectId, $this->practicalSubjectIds, true);
    }
}
