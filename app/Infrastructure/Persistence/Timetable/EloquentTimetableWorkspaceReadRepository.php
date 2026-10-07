<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTimetableWorkspaceReadRepository implements TimetableWorkspaceReadRepositoryInterface
{
    private const ACTIVE = 1;

    /** curriculum.subjects.subject_type 3 = عملي. */
    private const PRACTICAL = 3;

    /** The timetable shows a teacher as «الاسم واسم الأب» (short, still telling namesakes apart). */
    private const SHORT_NAME = "TRIM(CONCAT_WS(' ', t.first_name, t.father_name))";

    public function lessons(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        $curriculumSubjects = SchemaHelper::qualified('curriculum', 'curriculum_subjects');
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        // Weekly hours: the department curriculum of the year, else the general (department-less) one.
        $hours = static fn (string $departmentMatch): string => "(SELECT MAX(cs.weekly_hours) FROM {$curriculumSubjects} cs
            JOIN {$curricula} c ON c.id = cs.curriculum_id
            WHERE c.school_id = ta.school_id AND c.academic_year_id = ta.academic_year_id
              AND c.status = ".self::ACTIVE.' AND cs.status = '.self::ACTIVE." AND cs.subject_id = ta.subject_id AND {$departmentMatch})";

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments').' as ta')
            ->join(SchemaHelper::qualified('teachers', 'teachers').' as t', 't.id', '=', 'ta.teacher_id')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 'ta.subject_id')
            ->where('ta.school_id', $schoolId)
            ->where('ta.academic_year_id', $academicYearId)
            ->where('ta.status', TeachingAssignmentStatus::Active)
            ->whereNotNull('ta.class_id')
            ->orderBy('sub.name')
            ->orderBy('t.full_name')
            ->orderBy('ta.id')
            ->select([
                'ta.teacher_id', 'ta.subject_id', 'sub.name as subject_name',
                'ta.class_id', 'ta.section_id',
            ])
            ->selectRaw(self::SHORT_NAME.' AS teacher_name')
            ->selectRaw('COALESCE('.$hours('c.department_id = ta.department_id').', '.$hours('c.department_id IS NULL').') AS weekly_hours')
            ->get()
            ->map(static fn (object $r): array => [
                'teacher_id' => (int) $r->teacher_id,
                'teacher_name' => (string) $r->teacher_name,
                'subject_id' => (int) $r->subject_id,
                'subject_name' => (string) $r->subject_name,
                'class_id' => (int) $r->class_id,
                'section_id' => $r->section_id !== null ? (int) $r->section_id : null,
                'weekly_hours' => $r->weekly_hours !== null ? (int) $r->weekly_hours : null,
            ])
            ->all();
    }

    public function activeSchedules(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('lifecycle_status', ScheduleLifecycleStatus::Active->value)
            ->orderBy('id')
            ->get(['id', 'section_id', 'day_of_week', 'period_id', 'subject_id', 'teacher_id', 'room_id',
                'group_id', 'week_no', 'co_teacher_id', 'joined_to_schedule_id', 'locked_at', 'activity_id'])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'section_id' => (int) $r->section_id,
                'day_of_week' => (int) $r->day_of_week,
                'period_id' => (int) $r->period_id,
                'subject_id' => (int) $r->subject_id,
                'teacher_id' => (int) $r->teacher_id,
                'room_id' => $r->room_id !== null ? (int) $r->room_id : null,
                'group_id' => $r->group_id !== null ? (int) $r->group_id : null,
                'week_no' => $r->week_no !== null ? (int) $r->week_no : null,
                'co_teacher_id' => $r->co_teacher_id !== null ? (int) $r->co_teacher_id : null,
                'joined_to' => $r->joined_to_schedule_id !== null ? (int) $r->joined_to_schedule_id : null,
                'locked' => $r->locked_at !== null,
                'activity_id' => $r->activity_id !== null ? (int) $r->activity_id : null,
            ])
            ->all();
    }

    public function teachers(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teachers').' as t')
            ->join(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts', 'ts.teacher_id', '=', 't.id')
            ->where('ts.school_id', $schoolId)
            ->where('ts.academic_year_id', $academicYearId)
            ->whereNull('ts.left_at')
            ->where('t.status', self::ACTIVE)
            ->orderBy('t.full_name')
            ->orderBy('t.id')
            ->select(['t.id', 't.full_name'])
            ->selectRaw(self::SHORT_NAME.' AS short_name')
            ->get()
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'full_name' => (string) $r->full_name, 'short_name' => (string) $r->short_name])
            ->all();
    }

    public function sectionPlacements(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'e.branch_id')
            ->leftJoin(SchemaHelper::qualified('organization', 'departments').' as d', 'd.id', '=', 'e.department_id')
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->where('e.status', self::ACTIVE)
            ->whereNotNull('e.section_id')
            ->groupBy('e.section_id', 'e.branch_id', 'e.department_id')
            ->orderBy('e.section_id')
            ->orderBy('e.branch_id')
            ->orderBy('e.department_id')
            ->select(['e.section_id', 'e.branch_id', 'e.department_id'])
            ->selectRaw('COUNT(*) AS students')
            ->get()
            ->map(static fn (object $r): array => [
                'section_id' => (int) $r->section_id,
                'branch_id' => (int) $r->branch_id,
                'department_id' => $r->department_id !== null ? (int) $r->department_id : null,
                'students' => (int) $r->students,
            ])
            ->all();
    }

    public function teacherSubjects(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('teacher_id')
            ->orderBy('subject_id')
            ->get(['teacher_id', 'subject_id'])
            ->map(static fn (object $r): array => ['teacher_id' => (int) $r->teacher_id, 'subject_id' => (int) $r->subject_id])
            ->all();
    }

    public function practicalSubjectIds(): array
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('subject_type', self::PRACTICAL)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function sections(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->where('c.status', self::ACTIVE)
            ->where('s.status', self::ACTIVE)
            ->orderBy('s.id')
            ->get(['s.id', 's.class_id'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'class_id' => (int) $r->class_id])
            ->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
