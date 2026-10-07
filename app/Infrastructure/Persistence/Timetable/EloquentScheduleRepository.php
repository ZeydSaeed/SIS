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
use App\Domain\Timetable\ValueObjects\PeriodType;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentScheduleRepository implements ScheduleRepositoryInterface
{
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
     * its teacher and co-teacher (lead or co elsewhere; joined-class rows count once, on the lead row) and its
     * room — in the weeks the lessons meet (a lesson every week meets every week).
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

        if ($data->roomId !== null && (clone $leadRows)->where('room_id', $data->roomId)->exists()) {
            throw ScheduleSlotConflictException::forRoom();
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
