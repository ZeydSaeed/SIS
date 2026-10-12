<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\TeacherElsewhereRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cross-school busy times of shared teachers. `timetable.schedules` is FORCE RLS on `app.current_school_id`, so each
 * other school is read under its own (transaction-local) context, one school at a time, and the caller's context
 * is restored afterwards — even on failure. Only teacher id + day + clock times + week leave the other school.
 */
final class EloquentTeacherElsewhereRepository implements TeacherElsewhereRepositoryInterface
{
    private const ACTIVE_SCHOOL = 1;

    public function busyElsewhere(int $schoolId, int $academicYearId, array $teacherIds): array
    {
        $teacherIds = array_values(array_unique(array_filter($teacherIds, static fn (int $id): bool => $id > 0)));
        if ($teacherIds === []) {
            return [];
        }

        $others = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', '!=', $schoolId)
            ->where('status', self::ACTIVE_SCHOOL)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        if ($others === []) {
            return [];
        }

        $original = (string) (DB::selectOne("SELECT current_setting('app.current_school_id', true) AS v")->v ?? '');
        $busy = [];
        try {
            foreach ($others as $otherId) {
                DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $otherId]);
                $rows = DB::table(SchemaHelper::qualified('timetable', 'schedules').' as s')
                    ->join(SchemaHelper::qualified('timetable', 'periods').' as p', static function ($join): void {
                        $join->on('p.id', '=', 's.period_id')->on('p.school_id', '=', 's.school_id');
                    })
                    ->where('s.school_id', $otherId)
                    ->where('s.academic_year_id', $academicYearId)
                    ->whereNull('s.cancelled_at')
                    ->whereNull('s.joined_to_schedule_id')
                    ->where(static fn ($q) => $q->whereIn('s.teacher_id', $teacherIds)->orWhereIn('s.co_teacher_id', $teacherIds))
                    ->get(['s.teacher_id', 's.co_teacher_id', 's.day_of_week', 's.week_no', 'p.start_time', 'p.end_time']);
                foreach ($rows as $row) {
                    foreach ([$row->teacher_id, $row->co_teacher_id] as $teacherId) {
                        if ($teacherId !== null && in_array((int) $teacherId, $teacherIds, true)) {
                            $busy[] = [
                                'teacher_id' => (int) $teacherId,
                                'day' => (int) $row->day_of_week,
                                'start' => substr((string) $row->start_time, 0, 5),
                                'end' => substr((string) $row->end_time, 0, 5),
                                'week_no' => $row->week_no !== null ? (int) $row->week_no : null,
                            ];
                        }
                    }
                }
            }
        } finally {
            DB::statement("SELECT set_config('app.current_school_id', ?, true)", [$original]);
        }

        return $busy;
    }
}
