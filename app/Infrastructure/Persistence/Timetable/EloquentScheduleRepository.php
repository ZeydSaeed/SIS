<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Data\ScheduleSnapshot;
use App\Domain\Timetable\Exceptions\ScheduleNotActiveException;
use App\Domain\Timetable\Exceptions\ScheduleNotCancelledException;
use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Domain\Timetable\Exceptions\ScheduleSlotConflictException;
use App\Domain\Timetable\Exceptions\ScheduleValidationException;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Repositories\TeacherElsewhereRepositoryInterface;
use App\Domain\Timetable\ValueObjects\PeriodType;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
    private const UNAVAILABLE = 1;

    public function __construct(
        private readonly TeacherElsewhereRepositoryInterface $elsewhere,
    ) {}

    public function assertWritableRefs(PersistScheduleData $data): void
    {
        if ($data->dayOfWeek < 1 || $data->dayOfWeek > 7) {
            throw ScheduleValidationException::withReason('timetable.invalid_day_of_week');
        }

        $sectionOk = DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('s.id', $data->sectionId)
            ->where('c.school_id', $data->schoolId)
            ->where('c.academic_year_id', $data->academicYearId)
            ->exists();
        if (! $sectionOk) {
            throw ScheduleValidationException::withReason('timetable.section_not_in_school_year');
        }

        $periodType = DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('id', $data->periodId)
            ->where('school_id', $data->schoolId)
            ->value('period_type');
        if ($periodType === null) {
            throw ScheduleValidationException::withReason('timetable.period_not_in_school');
        }

        // Breaks («استراحة») hold no lessons.
        if ((int) $periodType !== PeriodType::Lesson->value) {
            throw ScheduleValidationException::withReason('timetable.period_not_lesson');
        }

        $subjectOk = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $data->subjectId)
            ->exists();
        if (! $subjectOk) {
            throw ScheduleValidationException::withReason('timetable.subject_not_found');
        }

        $teacherOk = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $data->teacherId)
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->exists();
        if (! $teacherOk) {
            throw ScheduleValidationException::withReason('timetable.teacher_not_in_school_year');
        }

        // G5 teacher binding: a teacher is scheduled only for subjects assigned to them (teachers page) in that year.
        $teachesSubject = DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('teacher_id', $data->teacherId)
            ->where('subject_id', $data->subjectId)
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->exists();
        if (! $teachesSubject) {
            throw ScheduleValidationException::withReason('timetable.teacher_not_assigned_subject');
        }

        if ($data->roomId !== null) {
            $roomOk = DB::table(SchemaHelper::qualified('organization', 'rooms').' as r')
                ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
                ->where('r.id', $data->roomId)
                ->where('b.school_id', $data->schoolId)
                ->exists();
            if (! $roomOk) {
                throw ScheduleValidationException::withReason('timetable.room_not_in_school');
            }
        }
    }

    /**
     * The slot must be free for the lesson's section (another group of the same division may share it),
     * its teacher and co-teacher (lead or co elsewhere; joined-class rows count once, on the lead row), its
     * room and its workshop — in the weeks the lessons meet (a lesson every week meets every week). A lesson
     * held in a workshop occupies the workshop and the room the workshop sits in; a lesson placed in that room
     * directly clashes with it too.
     */
    public function assertNoActiveSlotConflicts(PersistScheduleData $data, ?int $excludeScheduleId = null): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $base = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('day_of_week', $data->dayOfWeek)
            ->where('period_id', $data->periodId)
            ->whereNull('cancelled_at');

        if ($excludeScheduleId !== null) {
            $base->where('id', '!=', $excludeScheduleId);
            $base->where(static fn ($q) => $q->whereNull('joined_to_schedule_id')->orWhere('joined_to_schedule_id', '!=', $excludeScheduleId));
        }
        if ($data->weekNo !== null) {
            $base->where(static fn ($q) => $q->whereNull('week_no')->orWhere('week_no', $data->weekNo));
        }

        foreach ((clone $base)->where('section_id', $data->sectionId)->get(['group_id']) as $other) {
            if ($this->sectionLanesClash($data->groupId, $other->group_id !== null ? (int) $other->group_id : null)) {
                throw ScheduleSlotConflictException::forSection();
            }
        }

        $teachers = array_values(array_filter([$data->teacherId, $data->coTeacherId]));
        $leadRows = (clone $base)->whereNull('joined_to_schedule_id');
        if ((clone $leadRows)->where(static fn ($q) => $q->whereIn('teacher_id', $teachers)->orWhereIn('co_teacher_id', $teachers))->exists()) {
            throw ScheduleSlotConflictException::forTeacher();
        }

        $workshop = $this->workshopOfActivity($data->schoolId, $data->activityId);
        $rooms = array_values(array_unique(array_filter([$data->roomId, $workshop['room_id'] ?? null], static fn (?int $id): bool => $id !== null)));
        $activities = SchemaHelper::qualified('timetable', 'activities');
        $workshops = SchemaHelper::qualified('vocational', 'workshops');

        if ($rooms !== [] && (clone $leadRows)->where(static function ($q) use ($rooms, $activities, $workshops): void {
            $q->whereIn('room_id', $rooms)
                ->orWhereIn('activity_id', static fn ($sub) => $sub->select('a.id')->from($activities.' as a')
                    ->join($workshops.' as w', 'w.id', '=', 'a.workshop_id')
                    ->whereIn('w.room_id', $rooms));
        })->exists()) {
            throw ScheduleSlotConflictException::forRoom();
        }

        if ($workshop !== null && (clone $leadRows)->whereIn('activity_id', static fn ($sub) => $sub->select('id')->from($activities)
            ->where('workshop_id', $workshop['id']))->exists()) {
            throw ScheduleSlotConflictException::forWorkshop();
        }

        $this->assertNotMarkedUnavailable($data, $teachers, $rooms, $workshop['id'] ?? null);
        $this->assertTeachersFreeElsewhere($data->schoolId, $data->academicYearId, [[
            'teachers' => $teachers, 'day' => $data->dayOfWeek, 'period_id' => $data->periodId, 'week_no' => $data->weekNo,
        ]]);
    }

    /**
     * «غير متاح» (timetable.availability kind 1) is a hard stop for a placed lesson too, not only for the engine:
     * the teacher / co-teacher, the room (or the workshop's room), the workshop and the section.
     *
     * @param  list<int>  $teachers
     * @param  list<int>  $rooms
     */
    private function assertNotMarkedUnavailable(PersistScheduleData $data, array $teachers, array $rooms, ?int $workshopId): void
    {
        $marks = DB::table(SchemaHelper::qualified('timetable', 'availability'))
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('status', 1)
            ->where('kind', self::UNAVAILABLE)
            ->where('day_of_week', $data->dayOfWeek)
            ->where('period_id', $data->periodId);
        if ($data->weekNo !== null) {
            $marks->where(static fn ($q) => $q->whereNull('week_no')->orWhere('week_no', $data->weekNo));
        }
        $checks = [
            'teacher' => static fn ($q) => $q->whereIn('teacher_id', $teachers),
            'room' => static fn ($q) => $q->whereIn('room_id', $rooms === [] ? [0] : $rooms),
            'workshop' => static fn ($q) => $q->where('workshop_id', $workshopId ?? 0),
            'section' => static fn ($q) => $q->where('section_id', $data->sectionId),
        ];
        foreach ($checks as $target => $where) {
            if ((clone $marks)->where($where)->exists()) {
                throw ScheduleSlotConflictException::unavailable($target);
            }
        }
    }

    /**
     * A teacher shared with another school may not be placed while teaching there: the other school's lessons
     * (lead or co, same year, weeks that meet) whose clock times overlap this period.
     *
     * @param  list<array{teachers: list<int>, day: int, period_id: int, week_no: int|null}>  $lessons
     */
    private function assertTeachersFreeElsewhere(int $schoolId, int $academicYearId, array $lessons): void
    {
        $teacherIds = array_values(array_unique(array_merge(...array_map(static fn (array $l): array => $l['teachers'], $lessons))));
        $busy = $this->elsewhere->busyElsewhere($schoolId, $academicYearId, $teacherIds);
        if ($busy === []) {
            return;
        }
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $times = [];
        foreach (DB::table(SchemaHelper::qualified('timetable', 'periods'))->where('school_id', $schoolId)->get(['id', 'start_time', 'end_time']) as $p) {
            $times[(int) $p->id] = [substr((string) $p->start_time, 0, 5), substr((string) $p->end_time, 0, 5)];
        }
        foreach ($lessons as $lesson) {
            [$start, $end] = $times[$lesson['period_id']] ?? [null, null];
            if ($start === null) {
                continue;
            }
            foreach ($busy as $b) {
                if (in_array($b['teacher_id'], $lesson['teachers'], true)
                    && $b['day'] === $lesson['day']
                    && ($b['week_no'] === null || $lesson['week_no'] === null || $b['week_no'] === $lesson['week_no'])
                    && $b['start'] < $end && $start < $b['end']) {
                    throw ScheduleSlotConflictException::forTeacherElsewhere();
                }
            }
        }
    }

    /** @return array{id: int, room_id: int|null}|null the workshop an activity is held in */
    private function workshopOfActivity(int $schoolId, ?int $activityId): ?array
    {
        if ($activityId === null) {
            return null;
        }
        $row = DB::table(SchemaHelper::qualified('timetable', 'activities').' as a')
            ->join(SchemaHelper::qualified('vocational', 'workshops').' as w', 'w.id', '=', 'a.workshop_id')
            ->where('a.id', $activityId)
            ->where('a.school_id', $schoolId)
            ->first(['w.id', 'w.room_id']);

        return $row === null ? null : ['id' => (int) $row->id, 'room_id' => $row->room_id !== null ? (int) $row->room_id : null];
    }

    /**
     * Bulk writes (apply a generation run, restore a version) are checked as a whole once inserted: none of the
     * new lead lessons may share a slot and week with another active lesson of the same teacher (lead or co),
     * room (direct or the workshop's room), workshop, or section (except different groups of one division).
     * The unique indexes only cover part of this (one column each); any clash rolls the transaction back.
     *
     * @param  list<int>  $ids  the inserted lead lessons
     */
    private function assertNoClashesAmong(int $schoolId, int $academicYearId, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $schedules = SchemaHelper::qualified('timetable', 'schedules');
        $activities = SchemaHelper::qualified('timetable', 'activities');
        $workshops = SchemaHelper::qualified('vocational', 'workshops');
        $groups = SchemaHelper::qualified('timetable', 'division_groups');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $active = 's.school_id = ? AND s.academic_year_id = ? AND s.cancelled_at IS NULL';
        $weeksMeet = '(a.week_no IS NULL OR b.week_no IS NULL OR a.week_no = b.week_no)';
        $sameSlot = 'a.day_of_week = b.day_of_week AND a.period_id = b.period_id AND a.id <> b.id';
        $touchesNew = "(a.id IN ({$placeholders}) OR b.id IN ({$placeholders}))";
        $bind = static fn (int $times): array => array_merge(...array_fill(0, $times, [$schoolId, $academicYearId]));

        $checks = [
            'teacher' => "
                WITH busy AS (
                    SELECT s.id, s.day_of_week, s.period_id, s.week_no, s.teacher_id AS who FROM {$schedules} s
                    WHERE {$active} AND s.joined_to_schedule_id IS NULL
                    UNION ALL
                    SELECT s.id, s.day_of_week, s.period_id, s.week_no, s.co_teacher_id FROM {$schedules} s
                    WHERE {$active} AND s.joined_to_schedule_id IS NULL AND s.co_teacher_id IS NOT NULL
                )
                SELECT 1 FROM busy a JOIN busy b ON a.who = b.who AND {$sameSlot} AND {$weeksMeet} WHERE {$touchesNew} LIMIT 1",
            'room' => "
                WITH used AS (
                    SELECT s.id, s.day_of_week, s.period_id, s.week_no, s.room_id AS place FROM {$schedules} s
                    WHERE {$active} AND s.joined_to_schedule_id IS NULL AND s.room_id IS NOT NULL
                    UNION
                    SELECT s.id, s.day_of_week, s.period_id, s.week_no, w.room_id FROM {$schedules} s
                    JOIN {$activities} ac ON ac.id = s.activity_id JOIN {$workshops} w ON w.id = ac.workshop_id
                    WHERE {$active} AND s.joined_to_schedule_id IS NULL AND w.room_id IS NOT NULL
                )
                SELECT 1 FROM used a JOIN used b ON a.place = b.place AND {$sameSlot} AND {$weeksMeet} WHERE {$touchesNew} LIMIT 1",
            'workshop' => "
                WITH used AS (
                    SELECT s.id, s.day_of_week, s.period_id, s.week_no, ac.workshop_id AS place FROM {$schedules} s
                    JOIN {$activities} ac ON ac.id = s.activity_id
                    WHERE {$active} AND s.joined_to_schedule_id IS NULL AND ac.workshop_id IS NOT NULL
                )
                SELECT 1 FROM used a JOIN used b ON a.place = b.place AND {$sameSlot} AND {$weeksMeet} WHERE {$touchesNew} LIMIT 1",
            'section' => "
                WITH lanes AS (
                    SELECT s.id, s.section_id, s.day_of_week, s.period_id, s.week_no, s.group_id, g.division_id FROM {$schedules} s
                    LEFT JOIN {$groups} g ON g.id = s.group_id
                    WHERE {$active}
                )
                SELECT 1 FROM lanes a JOIN lanes b ON a.section_id = b.section_id AND {$sameSlot} AND {$weeksMeet}
                WHERE {$touchesNew}
                  AND NOT (a.group_id IS NOT NULL AND b.group_id IS NOT NULL AND a.group_id <> b.group_id AND a.division_id = b.division_id)
                LIMIT 1",
        ];
        $unions = ['teacher' => 2, 'room' => 2, 'workshop' => 1, 'section' => 1];

        foreach ($checks as $kind => $sql) {
            if (DB::selectOne($sql, [...$bind($unions[$kind]), ...$ids, ...$ids]) !== null) {
                throw match ($kind) {
                    'teacher' => ScheduleSlotConflictException::forTeacher(),
                    'room' => ScheduleSlotConflictException::forRoom(),
                    'workshop' => ScheduleSlotConflictException::forWorkshop(),
                    default => ScheduleSlotConflictException::forSection(),
                };
            }
        }
    }

    /** Two lessons of one section can share a slot only as different groups of one division. */
    private function sectionLanesClash(?int $groupA, ?int $groupB): bool
    {
        if ($groupA === null || $groupB === null || $groupA === $groupB) {
            return true;
        }
        $divisions = DB::table(SchemaHelper::qualified('timetable', 'division_groups'))->whereIn('id', [$groupA, $groupB])->pluck('division_id', 'id');

        return ! isset($divisions[$groupA], $divisions[$groupB]) || (int) $divisions[$groupA] !== (int) $divisions[$groupB];
    }

    public function insertActive(PersistScheduleData $data): int
    {
        $this->assertWritableRefs($data);
        $this->assertNoActiveSlotConflicts($data);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        try {
            return (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->insertGetId([
                'school_id' => $data->schoolId,
                'section_id' => $data->sectionId,
                'academic_year_id' => $data->academicYearId,
                'day_of_week' => $data->dayOfWeek,
                'period_id' => $data->periodId,
                'subject_id' => $data->subjectId,
                'teacher_id' => $data->teacherId,
                'room_id' => $data->roomId,
                'group_id' => $data->groupId,
                'week_no' => $data->weekNo,
                'co_teacher_id' => $data->coTeacherId,
                'activity_id' => $data->activityId,
                'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
                'cancelled_at' => null,
                'correlation_id' => $data->correlationId,
                'created_by' => $data->createdBy,
                'created_at' => $data->at,
                'updated_at' => $data->at,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ScheduleSlotConflictException::fromDatabase();
        }
    }

    private const SNAPSHOT_COLUMNS = [
        'id', 'school_id', 'section_id', 'academic_year_id', 'day_of_week', 'period_id', 'subject_id', 'teacher_id', 'room_id',
        'lifecycle_status', 'group_id', 'week_no', 'co_teacher_id', 'joined_to_schedule_id', 'locked_at', 'activity_id',
    ];

    public function findById(int $schoolId, int $scheduleId): ?ScheduleSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('id', $scheduleId)
            ->first(self::SNAPSHOT_COLUMNS);

        return $row === null ? null : self::snapshot($row);
    }

    private static function snapshot(object $row): ScheduleSnapshot
    {
        $nullable = static fn ($v): ?int => $v !== null ? (int) $v : null;

        return new ScheduleSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            sectionId: (int) $row->section_id,
            academicYearId: (int) $row->academic_year_id,
            dayOfWeek: (int) $row->day_of_week,
            periodId: (int) $row->period_id,
            subjectId: (int) $row->subject_id,
            teacherId: (int) $row->teacher_id,
            roomId: $nullable($row->room_id),
            lifecycleStatus: (int) $row->lifecycle_status,
            groupId: $nullable($row->group_id),
            weekNo: $nullable($row->week_no),
            coTeacherId: $nullable($row->co_teacher_id),
            joinedTo: $nullable($row->joined_to_schedule_id),
            locked: $row->locked_at !== null,
            activityId: $nullable($row->activity_id),
        );
    }

    /** A locked lesson stays where it is until it is unlocked. */
    private function assertNotLocked(int $schoolId, int $scheduleId): void
    {
        if ($this->findById($schoolId, $scheduleId)?->locked === true) {
            throw ScheduleValidationException::withReason('timetable.schedule_locked');
        }
    }

    public function setLocked(int $schoolId, array $scheduleIds, ?string $lockedAt, ?int $userId): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)->whereIn('id', $scheduleIds)->whereNull('cancelled_at')
            ->update(['locked_at' => $lockedAt, 'locked_by' => $lockedAt === null ? null : $userId, 'updated_at' => now()]);
    }

    public function replaceGrid(int $schoolId, int $academicYearId, array $cancelIds, array $rows, string $at, ?int $userId, ?string $correlationId): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $table = SchemaHelper::qualified('timetable', 'schedules');
        foreach (array_chunk($cancelIds, 500) as $chunk) {
            DB::table($table)->where('school_id', $schoolId)->whereIn('id', $chunk)->whereNull('locked_at')->whereNull('cancelled_at')
                ->update(['lifecycle_status' => ScheduleLifecycleStatus::Cancelled->value, 'cancelled_at' => $at, 'updated_at' => $at]);
        }

        $base = static fn (array $r): array => [
            'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'section_id' => $r['section_id'], 'group_id' => $r['group_id'],
            'day_of_week' => $r['day_of_week'], 'period_id' => $r['period_id'], 'week_no' => $r['week_no'], 'subject_id' => $r['subject_id'],
            'teacher_id' => $r['teacher_id'], 'co_teacher_id' => $r['co_teacher_id'], 'room_id' => $r['room_id'], 'activity_id' => $r['activity_id'],
            'lifecycle_status' => ScheduleLifecycleStatus::Active->value, 'correlation_id' => $correlationId, 'created_by' => $userId,
            'created_at' => $at, 'updated_at' => $at,
        ];
        $leadIds = [];
        try {
            foreach (array_filter($rows, static fn (array $r): bool => $r['is_lead']) as $row) {
                $leadIds[$row['lead_key']] = (int) DB::table($table)->insertGetId($base($row));
            }
            $joined = [];
            foreach ($rows as $row) {
                if (! $row['is_lead']) {
                    $joined[] = $base($row) + ['joined_to_schedule_id' => $leadIds[$row['lead_key']] ?? null];
                }
            }
            foreach (array_chunk($joined, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        } catch (UniqueConstraintViolationException) {
            throw ScheduleSlotConflictException::fromDatabase();
        }
        $this->assertNoClashesAmong($schoolId, $academicYearId, array_values($leadIds));
        $this->assertTeachersFreeElsewhere($schoolId, $academicYearId, array_values(array_map(static fn (array $r): array => [
            'teachers' => array_values(array_filter([$r['teacher_id'], $r['co_teacher_id']], static fn (?int $id): bool => $id !== null)),
            'day' => $r['day_of_week'], 'period_id' => $r['period_id'], 'week_no' => $r['week_no'],
        ], array_filter($rows, static fn (array $r): bool => $r['is_lead']))));

        return count($rows);
    }

    public function listForSchoolYear(
        int $schoolId,
        int $academicYearId,
        ?int $sectionId,
        ?int $lifecycleStatus,
        int $page,
        int $perPage,
    ): array {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $query = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        if ($sectionId !== null) {
            $query->where('section_id', $sectionId);
        }
        if ($lifecycleStatus !== null) {
            $query->where('lifecycle_status', $lifecycleStatus);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('day_of_week')
            ->orderBy('period_id')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get(self::SNAPSHOT_COLUMNS);

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::snapshot($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function updateActive(int $scheduleId, PersistScheduleData $data): void
    {
        $this->assertNotLocked($data->schoolId, $scheduleId);
        $this->assertWritableRefs($data);
        $this->assertNoActiveSlotConflicts($data, $scheduleId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        try {
            $updated = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
                ->where('id', $scheduleId)
                ->where('school_id', $data->schoolId)
                ->where('lifecycle_status', ScheduleLifecycleStatus::Active->value)
                ->whereNull('cancelled_at')
                ->update([
                    'section_id' => $data->sectionId,
                    'academic_year_id' => $data->academicYearId,
                    'day_of_week' => $data->dayOfWeek,
                    'period_id' => $data->periodId,
                    'subject_id' => $data->subjectId,
                    'teacher_id' => $data->teacherId,
                    'room_id' => $data->roomId,
                    'correlation_id' => $data->correlationId,
                    'updated_at' => $data->at,
                ]);
        } catch (UniqueConstraintViolationException) {
            throw ScheduleSlotConflictException::fromDatabase();
        }

        if ($updated === 0) {
            throw ScheduleNotActiveException::forId($scheduleId);
        }
        try {
            $this->moveJoinedRows($data->schoolId, $scheduleId, $data->dayOfWeek, $data->periodId, $data->at);
        } catch (UniqueConstraintViolationException) {
            throw ScheduleSlotConflictException::fromDatabase();
        }
    }

    /** Joined classes: the other sections' rows follow their lead row's slot. */
    private function moveJoinedRows(int $schoolId, int $leadId, int $day, int $periodId, string $at): void
    {
        DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)->where('joined_to_schedule_id', $leadId)->whereNull('cancelled_at')
            ->update(['day_of_week' => $day, 'period_id' => $periodId, 'updated_at' => $at]);
    }

    public function cancel(int $schoolId, int $scheduleId, string $cancelledAt): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $current = $this->findById($schoolId, $scheduleId);
        if ($current === null) {
            throw ScheduleNotFoundException::forId($scheduleId);
        }
        if ($current->lifecycleStatus !== ScheduleLifecycleStatus::Active->value) {
            throw ScheduleNotActiveException::forId($scheduleId);
        }
        if ($current->locked) {
            throw ScheduleValidationException::withReason('timetable.schedule_locked');
        }

        DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where(static fn ($q) => $q->where('id', $scheduleId)->orWhere('joined_to_schedule_id', $scheduleId))
            ->whereNull('cancelled_at')
            ->update([
                'lifecycle_status' => ScheduleLifecycleStatus::Cancelled->value,
                'cancelled_at' => $cancelledAt,
                'updated_at' => $cancelledAt,
            ]);
    }

    public function reactivate(int $schoolId, int $scheduleId, string $reactivatedAt): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $current = $this->findById($schoolId, $scheduleId);
        if ($current === null) {
            throw ScheduleNotFoundException::forId($scheduleId);
        }
        if ($current->lifecycleStatus !== ScheduleLifecycleStatus::Cancelled->value) {
            throw ScheduleNotCancelledException::forId($scheduleId);
        }

        $probe = new PersistScheduleData(
            schoolId: $current->schoolId,
            sectionId: $current->sectionId,
            academicYearId: $current->academicYearId,
            dayOfWeek: $current->dayOfWeek,
            periodId: $current->periodId,
            subjectId: $current->subjectId,
            teacherId: $current->teacherId,
            roomId: $current->roomId,
            at: $reactivatedAt,
            correlationId: null,
            createdBy: null,
            groupId: $current->groupId,
            weekNo: $current->weekNo,
            coTeacherId: $current->coTeacherId,
            activityId: $current->activityId,
        );
        $this->assertNoActiveSlotConflicts($probe, $scheduleId);

        try {
            DB::table(SchemaHelper::qualified('timetable', 'schedules'))
                ->where('id', $scheduleId)
                ->where('school_id', $schoolId)
                ->update([
                    'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
                    'cancelled_at' => null,
                    'updated_at' => $reactivatedAt,
                ]);
        } catch (UniqueConstraintViolationException) {
            throw ScheduleSlotConflictException::fromDatabase();
        }
    }

    public function relocate(int $schoolId, array $moves, string $at): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $table = SchemaHelper::qualified('timetable', 'schedules');

        $current = [];
        foreach ($moves as $move) {
            $snapshot = $this->findById($schoolId, $move['schedule_id']);
            if ($snapshot === null) {
                throw ScheduleNotFoundException::forId($move['schedule_id']);
            }
            if ($snapshot->lifecycleStatus !== ScheduleLifecycleStatus::Active->value) {
                throw ScheduleNotActiveException::forId($move['schedule_id']);
            }
            if ($snapshot->locked) {
                throw ScheduleValidationException::withReason('timetable.schedule_locked');
            }
            $current[$move['schedule_id']] = $snapshot;
        }

        // Step aside first (the active-slot unique indexes are checked row by row), then land one by one.
        DB::table($table)->where('school_id', $schoolId)->whereIn('id', array_keys($current))->update([
            'lifecycle_status' => ScheduleLifecycleStatus::Cancelled->value,
            'cancelled_at' => $at,
        ]);

        foreach ($moves as $move) {
            $snapshot = $current[$move['schedule_id']];
            $data = new PersistScheduleData(
                schoolId: $schoolId,
                sectionId: $snapshot->sectionId,
                academicYearId: $snapshot->academicYearId,
                dayOfWeek: $move['day'],
                periodId: $move['period_id'],
                subjectId: $snapshot->subjectId,
                teacherId: $snapshot->teacherId,
                roomId: $snapshot->roomId,
                at: $at,
                correlationId: null,
                createdBy: null,
                groupId: $snapshot->groupId,
                weekNo: $snapshot->weekNo,
                coTeacherId: $snapshot->coTeacherId,
                activityId: $snapshot->activityId,
            );
            $this->assertWritableRefs($data);
            $this->assertNoActiveSlotConflicts($data, $snapshot->id);

            try {
                DB::table($table)->where('school_id', $schoolId)->where('id', $snapshot->id)->update([
                    'day_of_week' => $move['day'],
                    'period_id' => $move['period_id'],
                    'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
                    'cancelled_at' => null,
                    'updated_at' => $at,
                ]);
                $this->moveJoinedRows($schoolId, $snapshot->id, $move['day'], $move['period_id'], $at);
            } catch (UniqueConstraintViolationException) {
                throw ScheduleSlotConflictException::fromDatabase();
            }
        }
    }
}
