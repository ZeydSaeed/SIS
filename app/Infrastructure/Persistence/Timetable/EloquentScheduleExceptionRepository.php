<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Data\PersistScheduleExceptionData;
use App\Domain\Timetable\Data\ScheduleExceptionSnapshot;
use App\Domain\Timetable\Exceptions\ScheduleNotActiveException;
use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Domain\Timetable\Exceptions\ScheduleValidationException;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentScheduleExceptionRepository implements ScheduleExceptionRepositoryInterface
{
    public function insert(PersistScheduleExceptionData $data): int
    {
        $this->assertWritable($data);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        return (int) DB::table(SchemaHelper::qualified('timetable', 'schedule_exceptions'))->insertGetId([
            'school_id' => $data->schoolId,
            'schedule_id' => $data->scheduleId,
            'exception_date' => $data->exceptionDate,
            'substitute_teacher_id' => $data->substituteTeacherId,
            'substitute_room_id' => $data->substituteRoomId,
            'reason' => $data->reason,
            'correlation_id' => $data->correlationId,
            'created_by' => $data->createdBy,
            'created_at' => $data->at,
            'updated_at' => $data->at,
        ]);
    }

    public function update(int $exceptionId, PersistScheduleExceptionData $data): void
    {
        $this->assertWritable($data);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $updated = DB::table(SchemaHelper::qualified('timetable', 'schedule_exceptions'))
            ->where('id', $exceptionId)
            ->where('school_id', $data->schoolId)
            ->update([
                'exception_date' => $data->exceptionDate,
                'substitute_teacher_id' => $data->substituteTeacherId,
                'substitute_room_id' => $data->substituteRoomId,
                'reason' => $data->reason,
                'correlation_id' => $data->correlationId,
                'updated_at' => $data->at,
            ]);

        if ($updated === 0) {
            throw ScheduleValidationException::withReason('timetable.schedule_exception_not_found');
        }
    }

    public function existsForScheduleDate(int $schoolId, int $scheduleId, string $exceptionDate): bool
    {
        return DB::table(SchemaHelper::qualified('timetable', 'schedule_exceptions'))
            ->where('school_id', $schoolId)
            ->where('schedule_id', $scheduleId)
            ->where('exception_date', $exceptionDate)
            ->exists();
    }

    public function findById(int $schoolId, int $exceptionId): ?ScheduleExceptionSnapshot
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $row = DB::table(SchemaHelper::qualified('timetable', 'schedule_exceptions'))
            ->where('school_id', $schoolId)
            ->where('id', $exceptionId)
            ->first([
                'id', 'school_id', 'schedule_id', 'exception_date',
                'substitute_teacher_id', 'substitute_room_id', 'reason',
            ]);

        if ($row === null) {
            return null;
        }

        return $this->toSnapshot($row);
    }

    public function listForSchool(
        int $schoolId,
        ?int $scheduleId,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage,
    ): array {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $query = DB::table(SchemaHelper::qualified('timetable', 'schedule_exceptions'))
            ->where('school_id', $schoolId);

        if ($scheduleId !== null) {
            $query->where('schedule_id', $scheduleId);
        }
        if ($dateFrom !== null) {
            $query->where('exception_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('exception_date', '<=', $dateTo);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('exception_date')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get([
                'id', 'school_id', 'schedule_id', 'exception_date',
                'substitute_teacher_id', 'substitute_room_id', 'reason',
            ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->toSnapshot($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    private function toSnapshot(object $row): ScheduleExceptionSnapshot
    {
        $rawDate = $row->exception_date;
        if ($rawDate instanceof \DateTimeInterface) {
            $exceptionDate = $rawDate->format('Y-m-d');
        } else {
            $exceptionDate = substr((string) $rawDate, 0, 10);
        }

        return new ScheduleExceptionSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            scheduleId: (int) $row->schedule_id,
            exceptionDate: $exceptionDate,
            substituteTeacherId: $row->substitute_teacher_id !== null ? (int) $row->substitute_teacher_id : null,
            substituteRoomId: $row->substitute_room_id !== null ? (int) $row->substitute_room_id : null,
            reason: $row->reason !== null ? (string) $row->reason : null,
        );
    }

    private function assertWritable(PersistScheduleExceptionData $data): void
    {
        $schedule = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('id', $data->scheduleId)
            ->where('school_id', $data->schoolId)
            ->first(['id', 'lifecycle_status', 'academic_year_id']);

        if ($schedule === null) {
            throw ScheduleNotFoundException::forId($data->scheduleId);
        }
        if ((int) $schedule->lifecycle_status !== ScheduleLifecycleStatus::Active->value) {
            throw ScheduleNotActiveException::forId($data->scheduleId);
        }

        if ($data->substituteTeacherId !== null) {
            $ok = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
                ->where('teacher_id', $data->substituteTeacherId)
                ->where('school_id', $data->schoolId)
                ->where('academic_year_id', (int) $schedule->academic_year_id)
                ->exists();
            if (! $ok) {
                throw ScheduleValidationException::withReason('timetable.substitute_teacher_not_in_school_year');
            }
        }

        if ($data->substituteRoomId !== null) {
            $ok = DB::table(SchemaHelper::qualified('organization', 'rooms').' as r')
                ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
                ->where('r.id', $data->substituteRoomId)
                ->where('b.school_id', $data->schoolId)
                ->exists();
            if (! $ok) {
                throw ScheduleValidationException::withReason('timetable.substitute_room_not_in_school');
            }
        }
    }
}
