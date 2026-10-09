<?php

namespace App\Domain\Timetable\Data;

use App\Domain\Timetable\Services\PeriodTimeGuard;
use App\Domain\Timetable\Support\TimetableSettings;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * The school-year timetable as plain data, for the pure timetable services (audit, advice, solver, …).
 *
 * - periods: the school day in order (lessons and breaks);
 * - schedules: active lessons on the grid (optional keys: room_id, group_id, week_no, co_teacher_id,
 *   joined_to (lead row of joined classes), locked, activity_id);
 * - requirements: per section, a subject taught by a teacher `weekly` times a week (null = not set) —
 *   derived from teaching assignments × curriculum hours;
 * - teacherSubjects: "teacher:subject" keys the teacher may teach (teacher_subjects);
 * - activeTeacherIds: teachers active in the school this year;
 * - practicalSubjectIds: subjects of type «عملي» (taught as consecutive doubles by default);
 * - sectionIds: the active sections of the year, with or without lessons (empty = not loaded);
 * - settings: working days, cycle, daily limits, double changeover, weights ({@see TimetableSettings});
 * - activities: configured activities (timetable.activities with targets and teachers);
 * - groups: group id → division / section / student count;
 * - availability: unavailable / avoid / preferred slots;
 * - rules: active constraint rules;
 * - rooms / workshops: id → capacity (+ room type / workshop's room and safety capacity);
 * - sectionInfo: section id → class, grade, branches, departments, students (rule scopes, capacity);
 * - teacherLimits: teacher id → personal weekly min / weekly max / daily max lessons (teacher_schools; a missing
 *   key or a null limit = no personal limit).
 */
final readonly class TimetableBoard
{
    /** @var list<int> lesson period ids in day order */
    public array $lessonPeriodIds;

    public TimetableSettings $settings;

    /** @var array<int, PeriodSnapshot> */
    private array $periodsById;

    /**
     * @param  list<PeriodSnapshot>  $periods
     * @param  list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int, room_id?: int|null, group_id?: int|null, week_no?: int|null, co_teacher_id?: int|null, joined_to?: int|null, locked?: bool, activity_id?: int|null}>  $schedules
     * @param  list<array{section_id: int, subject_id: int, teacher_id: int, weekly: int|null}>  $requirements
     * @param  array<string, true>  $teacherSubjects
     * @param  list<int>  $activeTeacherIds
     * @param  list<int>  $practicalSubjectIds
     * @param  list<int>  $sectionIds
     * @param  list<array{id: int, subject_id: int, activity_type: int, weekly: int, block: int, distribution: list<int>|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int, targets: list<array{section_id: int, group_id: int|null}>, teachers: list<array{teacher_id: int, role: int, sessions: int|null}>}>  $activities
     * @param  array<int, array{id: int, division_id: int, section_id: int, name: string, student_count: int|null}>  $groups
     * @param  list<array{teacher_id: int|null, room_id: int|null, section_id: int|null, workshop_id: int|null, day: int, period_id: int, week_no: int|null, kind: int}>  $availability
     * @param  list<array{id: int, rule_type: string, priority: int, scope: array<string, int|null>, params: array<string, mixed>}>  $rules
     * @param  array<int, array{id: int, capacity: int|null, room_type: int|null}>  $rooms
     * @param  array<int, array{id: int, capacity: int, safety_capacity: int, room_id: int|null}>  $workshops
     * @param  array<int, array{class_id: int, grade_level_id: int|null, branch_ids: list<int>, department_ids: list<int>, students: int}>  $sectionInfo
     * @param  array<int, array{weekly_min: int|null, weekly_max: int|null, daily_max: int|null}>  $teacherLimits
     */
    public function __construct(
        public array $periods,
        public array $schedules,
        public array $requirements,
        public array $teacherSubjects,
        public array $activeTeacherIds,
        public array $practicalSubjectIds,
        public array $sectionIds = [],
        ?TimetableSettings $settings = null,
        public array $activities = [],
        public array $groups = [],
        public array $availability = [],
        public array $rules = [],
        public array $rooms = [],
        public array $workshops = [],
        public array $sectionInfo = [],
        public array $teacherLimits = [],
    ) {
        $this->settings = $settings ?? TimetableSettings::defaults();
        $ordered = $periods;
        usort($ordered, static fn (PeriodSnapshot $a, PeriodSnapshot $b): int => $a->periodNumber <=> $b->periodNumber);
        $this->lessonPeriodIds = array_values(array_map(
            static fn (PeriodSnapshot $p): int => $p->id,
            array_filter($ordered, static fn (PeriodSnapshot $p): bool => $p->periodType === PeriodType::Lesson->value),
        ));
        $byId = [];
        foreach ($periods as $period) {
            $byId[$period->id] = $period;
        }
        $this->periodsById = $byId;
    }

    /**
     * True when the second lesson follows the first with at most the short changeover of the settings
     * (default 10 minutes) — a double. The main break splits it.
     */
    public function adjacent(int $firstPeriodId, int $secondPeriodId): bool
    {
        $index = array_search($firstPeriodId, $this->lessonPeriodIds, true);
        if ($index === false || ($this->lessonPeriodIds[$index + 1] ?? null) !== $secondPeriodId) {
            return false;
        }

        $end = PeriodTimeGuard::minutes($this->periodsById[$firstPeriodId]->endTime);
        $start = PeriodTimeGuard::minutes($this->periodsById[$secondPeriodId]->startTime);

        return $end !== null && $start !== null && $start - $end <= $this->settings->doubleChangeoverMinutes;
    }

    /** The same board with another grid (verification of a proposal, what-if). */
    public function withSchedules(array $schedules): self
    {
        return new self($this->periods, $schedules, $this->requirements, $this->teacherSubjects, $this->activeTeacherIds,
            $this->practicalSubjectIds, $this->sectionIds, $this->settings, $this->activities, $this->groups, $this->availability,
            $this->rules, $this->rooms, $this->workshops, $this->sectionInfo, $this->teacherLimits);
    }

    /** The same board with extra availability rows (what-if: an absence, a closed room). */
    public function withAvailability(array $extra): self
    {
        return new self($this->periods, $this->schedules, $this->requirements, $this->teacherSubjects, $this->activeTeacherIds,
            $this->practicalSubjectIds, $this->sectionIds, $this->settings, $this->activities, $this->groups, [...$this->availability, ...$extra],
            $this->rules, $this->rooms, $this->workshops, $this->sectionInfo, $this->teacherLimits);
    }

    /** The same board with extra rules (generation objectives). */
    public function withRules(array $extra): self
    {
        return new self($this->periods, $this->schedules, $this->requirements, $this->teacherSubjects, $this->activeTeacherIds,
            $this->practicalSubjectIds, $this->sectionIds, $this->settings, $this->activities, $this->groups, $this->availability,
            [...$this->rules, ...$extra], $this->rooms, $this->workshops, $this->sectionInfo, $this->teacherLimits);
    }

    /** The same board with exactly these rules (a generation level may keep only the hard ones). */
    public function replaceRules(array $rules): self
    {
        return new self($this->periods, $this->schedules, $this->requirements, $this->teacherSubjects, $this->activeTeacherIds,
            $this->practicalSubjectIds, $this->sectionIds, $this->settings, $this->activities, $this->groups, $this->availability,
            $rules, $this->rooms, $this->workshops, $this->sectionInfo, $this->teacherLimits);
    }

    /**
     * Lessons a week the teacher can take: the week's slots and the daily limit (personal, else the school's)
     * cap it, and so does the personal weekly maximum.
     */
    public function weeklyCapacity(int $teacherId): int
    {
        $days = count($this->settings->days);
        $daily = $this->dailyLimit($teacherId);
        $capacity = min($days * count($this->lessonPeriodIds), $days * $daily);
        $max = $this->teacherLimits[$teacherId]['weekly_max'] ?? null;

        return $max === null ? $capacity : min($capacity, $max);
    }

    /** Lessons a day: the teacher's personal limit, else the school-wide one. */
    public function dailyLimit(int $teacherId): int
    {
        return $this->teacherLimits[$teacherId]['daily_max'] ?? $this->settings->maxTeacherPerDay;
    }

    public function isPractical(int $subjectId): bool
    {
        return in_array($subjectId, $this->practicalSubjectIds, true);
    }

    /** The lesson's place in the day (1 = first lesson), null for a break or unknown period. */
    public function lessonOrdinal(int $periodId): ?int
    {
        $index = array_search($periodId, $this->lessonPeriodIds, true);

        return $index === false ? null : $index + 1;
    }

    /** The division of a group (null = whole section / unknown group). */
    public function divisionOf(?int $groupId): ?int
    {
        return $groupId === null ? null : ($this->groups[$groupId]['division_id'] ?? null);
    }

    /**
     * Two lessons of one section in the same slot can stand together only as different groups of one
     * division, or in weeks that never meet.
     *
     * @param  array{group_id?: int|null, week_no?: int|null}  $a
     * @param  array{group_id?: int|null, week_no?: int|null}  $b
     */
    public function sectionLessonsClash(array $a, array $b): bool
    {
        if (! self::weeksMeet($a['week_no'] ?? null, $b['week_no'] ?? null)) {
            return false;
        }
        $groupA = $a['group_id'] ?? null;
        $groupB = $b['group_id'] ?? null;
        if ($groupA === null || $groupB === null || $groupA === $groupB) {
            return true;
        }

        return $this->divisionOf($groupA) === null || $this->divisionOf($groupA) !== $this->divisionOf($groupB);
    }

    /** A lesson every week (null) meets every week; two week-bound lessons meet only in the same week. */
    public static function weeksMeet(?int $a, ?int $b): bool
    {
        return $a === null || $b === null || $a === $b;
    }

    /**
     * Teachers a lesson keeps busy: the lead row's teacher and co-teacher. The other sections' rows of
     * joined classes point at the lead row and keep nobody busy a second time.
     *
     * @param  array{teacher_id: int, co_teacher_id?: int|null, joined_to?: int|null}  $schedule
     * @return list<int>
     */
    public static function busyTeachers(array $schedule): array
    {
        if (($schedule['joined_to'] ?? null) !== null) {
            return [];
        }

        return array_values(array_filter([$schedule['teacher_id'], $schedule['co_teacher_id'] ?? null], static fn (?int $t): bool => $t !== null));
    }
}
