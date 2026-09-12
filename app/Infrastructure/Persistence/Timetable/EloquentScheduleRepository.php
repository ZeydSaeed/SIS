<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Data\ScheduleSnapshot;
use App\Domain\Timetable\Exceptions\ScheduleNotActiveException;
use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Domain\Timetable\Exceptions\ScheduleValidationException;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
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

        $periodOk = DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('id', $data->periodId)
            ->where('school_id', $data->schoolId)
            ->exists();
        if (! $periodOk) {
            throw ScheduleValidationException::withReason('timetable.period_not_in_school');
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

    public function insertActive(PersistScheduleData $data): int
    {
        $this->assertWritableRefs($data);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        return (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))->insertGetId([
            'school_id' => $data->schoolId,
            'section_id' => $data->sectionId,
            'academic_year_id' => $data->academicYearId,
            'day_of_week' => $data->dayOfWeek,
            'period_id' => $data->periodId,
            'subject_id' => $data->subjectId,
            'teacher_id' => $data->teacherId,
            'room_id' => $data->roomId,
            'lifecycle_status' => ScheduleLifecycleStatus::Active->value,
            'cancelled_at' => null,
            'correlation_id' => $data->correlationId,
            'created_by' => $data->createdBy,
            'created_at' => $data->at,
            'updated_at' => $data->at,
        ]);
    }

    public function findById(int $schoolId, int $scheduleId): ?ScheduleSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('id', $scheduleId)
            ->first([
                'id', 'school_id', 'section_id', 'academic_year_id', 'day_of_week',
                'period_id', 'subject_id', 'teacher_id', 'room_id', 'lifecycle_status',
            ]);

        if ($row === null) {
            return null;
        }

        return new ScheduleSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            sectionId: (int) $row->section_id,
            academicYearId: (int) $row->academic_year_id,
            dayOfWeek: (int) $row->day_of_week,
            periodId: (int) $row->period_id,
            subjectId: (int) $row->subject_id,
            teacherId: (int) $row->teacher_id,
            roomId: $row->room_id !== null ? (int) $row->room_id : null,
            lifecycleStatus: (int) $row->lifecycle_status,
        );
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
            ->get([
                'id', 'school_id', 'section_id', 'academic_year_id', 'day_of_week',
                'period_id', 'subject_id', 'teacher_id', 'room_id', 'lifecycle_status',
            ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = new ScheduleSnapshot(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                sectionId: (int) $row->section_id,
                academicYearId: (int) $row->academic_year_id,
                dayOfWeek: (int) $row->day_of_week,
                periodId: (int) $row->period_id,
                subjectId: (int) $row->subject_id,
                teacherId: (int) $row->teacher_id,
                roomId: $row->room_id !== null ? (int) $row->room_id : null,
                lifecycleStatus: (int) $row->lifecycle_status,
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function updateActive(int $scheduleId, PersistScheduleData $data): void
    {
        $this->assertWritableRefs($data);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

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

        if ($updated === 0) {
            throw ScheduleNotActiveException::forId($scheduleId);
        }
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

        DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('id', $scheduleId)
            ->where('school_id', $schoolId)
            ->update([
                'lifecycle_status' => ScheduleLifecycleStatus::Cancelled->value,
                'cancelled_at' => $cancelledAt,
                'updated_at' => $cancelledAt,
            ]);
    }
}
