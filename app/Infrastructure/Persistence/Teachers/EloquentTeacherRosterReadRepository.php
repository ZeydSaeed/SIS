<?php

namespace App\Infrastructure\Persistence\Teachers;

use App\Database\SchemaHelper;
use App\Domain\Teachers\Repositories\TeacherRosterReadRepositoryInterface;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTeacherRosterReadRepository implements TeacherRosterReadRepositoryInterface
{
    public function roster(int $schoolId, int $academicYearId, int $limit): array
    {
        $this->bindSchool($schoolId);

        $base = DB::table(SchemaHelper::qualified('teachers', 'teachers').' as t')
            ->join(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts', 'ts.teacher_id', '=', 't.id')
            ->where('ts.school_id', $schoolId)
            ->where('ts.academic_year_id', $academicYearId)
            ->whereNull('ts.left_at');

        $total = (int) (clone $base)->count('t.id');
        $rows = $base
            ->orderBy('t.full_name')
            ->orderBy('t.id')
            ->limit($limit)
            ->get([
                't.id', 't.employee_code', 't.first_name', 't.father_name', 't.grandfather_name', 't.last_name',
                't.full_name', 't.national_id', 't.specialization_field', 't.hire_date', 't.status',
                'ts.is_primary', 'ts.employment_type',
                'ts.weekly_lessons_min', 'ts.weekly_lessons_max', 'ts.daily_lessons_max',
                't.abbreviation', 't.color_hue', 't.academic_title_id',
            ]);

        return [
            'items' => $rows->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'employee_code' => (string) $r->employee_code,
                'first_name' => (string) $r->first_name,
                'father_name' => $r->father_name !== null ? (string) $r->father_name : null,
                'grandfather_name' => $r->grandfather_name !== null ? (string) $r->grandfather_name : null,
                'last_name' => (string) $r->last_name,
                'full_name' => (string) $r->full_name,
                'national_id' => $r->national_id !== null ? (string) $r->national_id : null,
                'specialization_field' => $r->specialization_field !== null ? (string) $r->specialization_field : null,
                'hire_date' => $r->hire_date !== null ? substr((string) $r->hire_date, 0, 10) : null,
                'status' => (int) $r->status,
                'is_primary' => (bool) $r->is_primary,
                'employment_type' => $r->employment_type !== null ? (int) $r->employment_type : null,
                'weekly_lessons_min' => $r->weekly_lessons_min !== null ? (int) $r->weekly_lessons_min : null,
                'weekly_lessons_max' => $r->weekly_lessons_max !== null ? (int) $r->weekly_lessons_max : null,
                'daily_lessons_max' => $r->daily_lessons_max !== null ? (int) $r->daily_lessons_max : null,
                'abbreviation' => $r->abbreviation !== null ? (string) $r->abbreviation : null,
                'color_hue' => $r->color_hue !== null ? (int) $r->color_hue : null,
                'academic_title_id' => $r->academic_title_id !== null ? (int) $r->academic_title_id : null,
            ])->all(),
            'total' => $total,
        ];
    }

    public function teachingAssignments(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments').' as ta')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 'ta.subject_id')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'ta.branch_id')
            ->leftJoin(SchemaHelper::qualified('organization', 'departments').' as d', 'd.id', '=', 'ta.department_id')
            ->leftJoin(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 'ta.class_id')
            ->leftJoin(SchemaHelper::qualified('enrollment', 'sections').' as s', 's.id', '=', 'ta.section_id')
            ->where('ta.school_id', $schoolId)
            ->where('ta.academic_year_id', $academicYearId)
            ->where('ta.status', TeachingAssignmentStatus::Active)
            ->orderBy('ta.teacher_id')
            ->orderBy('sub.name')
            ->orderBy('ta.id')
            ->get([
                'ta.id', 'ta.teacher_id', 'ta.subject_id', 'sub.name as subject_name',
                'ta.branch_id', 'b.name as branch_name', 'ta.department_id', 'd.name as department_name',
                'ta.class_id', 'c.name as class_name', 'ta.section_id', 's.name as section_name', 'ta.effective_from',
            ])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'teacher_id' => (int) $r->teacher_id,
                'subject_id' => (int) $r->subject_id,
                'subject_name' => (string) $r->subject_name,
                'branch_id' => (int) $r->branch_id,
                'branch_name' => (string) $r->branch_name,
                'department_id' => $r->department_id !== null ? (int) $r->department_id : null,
                'department_name' => $r->department_name !== null ? (string) $r->department_name : null,
                'class_id' => $r->class_id !== null ? (int) $r->class_id : null,
                'class_name' => $r->class_name !== null ? (string) $r->class_name : null,
                'section_id' => $r->section_id !== null ? (int) $r->section_id : null,
                'section_name' => $r->section_name !== null ? (string) $r->section_name : null,
                'effective_from' => substr((string) $r->effective_from, 0, 10),
            ])
            ->all();
    }

    public function curriculumSubjectIds(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->where('c.status', 1)
            ->where('cs.status', 1)
            ->distinct()
            ->get(['c.department_id', 'cs.subject_id']);

        $general = [];
        $byDepartment = [];
        foreach ($rows as $row) {
            if ($row->department_id === null) {
                $general[] = (int) $row->subject_id;
            } else {
                $byDepartment[(int) $row->department_id][] = (int) $row->subject_id;
            }
        }

        return ['general' => array_values(array_unique($general)), 'by_department' => $byDepartment];
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
