<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Support\TimetableSettings;
use Illuminate\Support\Facades\DB;

final class EloquentTimetableEngineReadRepository implements TimetableEngineReadRepositoryInterface
{
    private const ACTIVE = 1;

    public function settings(int $schoolId, int $academicYearId): ?TimetableSettings
    {
        $this->bindSchool($schoolId);
        $row = DB::table(SchemaHelper::qualified('timetable', 'configs'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->first(['working_days', 'cycle_weeks', 'max_teacher_per_day', 'max_subject_per_day', 'double_changeover_minutes', 'weights']);
        if ($row === null) {
            return null;
        }
        $days = array_map('intval', (array) json_decode((string) $row->working_days, true));
        sort($days);
        $weights = [];
        foreach ((array) json_decode((string) ($row->weights ?? '{}'), true) as $priority => $weight) {
            $weights[(int) $priority] = (int) $weight;
        }

        return new TimetableSettings(
            days: $days,
            cycleWeeks: (int) $row->cycle_weeks,
            maxTeacherPerDay: (int) $row->max_teacher_per_day,
            maxSubjectPerDay: (int) $row->max_subject_per_day,
            doubleChangeoverMinutes: (int) $row->double_changeover_minutes,
            weights: $weights,
        );
    }

    public function display(int $schoolId, int $academicYearId): ?array
    {
        $this->bindSchool($schoolId);
        $raw = DB::table(SchemaHelper::qualified('timetable', 'configs'))
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->value('display');

        return $raw === null ? null : (array) json_decode((string) $raw, true);
    }

    public function activities(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);
        $rows = DB::table(SchemaHelper::qualified('timetable', 'activities'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->orderBy('id')
            ->get(['id', 'subject_id', 'activity_type', 'weekly_count', 'block_length', 'distribution', 'distribution_fixed', 'room_id', 'room_type', 'workshop_id', 'week_pattern', 'term_id', 'note']);
        if ($rows->isEmpty()) {
            return [];
        }
        $ids = $rows->pluck('id')->all();
        $targets = DB::table(SchemaHelper::qualified('timetable', 'activity_sections'))
            ->whereIn('activity_id', $ids)->orderBy('id')
            ->get(['activity_id', 'section_id', 'group_id'])
            ->groupBy('activity_id');
        $teachers = DB::table(SchemaHelper::qualified('timetable', 'activity_teachers'))
            ->whereIn('activity_id', $ids)->orderBy('role')->orderBy('id')
            ->get(['activity_id', 'teacher_id', 'role', 'sessions'])
            ->groupBy('activity_id');

        return $rows->map(static fn (object $r): array => [
            'id' => (int) $r->id,
            'subject_id' => (int) $r->subject_id,
            'activity_type' => (int) $r->activity_type,
            'weekly' => (int) $r->weekly_count,
            'block' => (int) $r->block_length,
            'distribution' => $r->distribution !== null ? array_map('intval', explode('+', (string) $r->distribution)) : null,
            'distribution_text' => $r->distribution,
            'room_id' => $r->room_id !== null ? (int) $r->room_id : null,
            'room_type' => $r->room_type !== null ? (int) $r->room_type : null,
            'workshop_id' => $r->workshop_id !== null ? (int) $r->workshop_id : null,
            'week_pattern' => (int) $r->week_pattern,
            'term_id' => $r->term_id !== null ? (int) $r->term_id : null,
            'note' => $r->note,
            'targets' => ($targets[$r->id] ?? collect())->map(static fn (object $t): array => [
                'section_id' => (int) $t->section_id,
                'group_id' => $t->group_id !== null ? (int) $t->group_id : null,
            ])->values()->all(),
            'teachers' => ($teachers[$r->id] ?? collect())->map(static fn (object $t): array => [
                'teacher_id' => (int) $t->teacher_id,
                'role' => (int) $t->role,
                'sessions' => $t->sessions !== null ? (int) $t->sessions : null,
            ])->values()->all(),
        ])->all();
    }

    public function groups(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);
        $members = DB::table(SchemaHelper::qualified('timetable', 'group_members'))
            ->where('school_id', $schoolId)->where('status', self::ACTIVE)
            ->groupBy('group_id')->selectRaw('group_id, COUNT(*) AS members')
            ->pluck('members', 'group_id');

        $groups = [];
        foreach (DB::table(SchemaHelper::qualified('timetable', 'division_groups').' as g')
            ->join(SchemaHelper::qualified('timetable', 'divisions').' as d', 'd.id', '=', 'g.division_id')
            ->where('d.school_id', $schoolId)
            ->where('d.academic_year_id', $academicYearId)
            ->where('d.status', self::ACTIVE)
            ->where('g.status', self::ACTIVE)
            ->orderBy('d.section_id')->orderBy('d.id')->orderBy('g.id')
            ->get(['g.id', 'g.division_id', 'd.section_id', 'g.name', 'g.student_count', 'd.name as division_name']) as $g) {
            $groups[(int) $g->id] = [
                'id' => (int) $g->id,
                'division_id' => (int) $g->division_id,
                'section_id' => (int) $g->section_id,
                'name' => (string) $g->name,
                'student_count' => $g->student_count !== null ? (int) $g->student_count : null,
                'division_name' => (string) $g->division_name,
                'members' => (int) ($members[$g->id] ?? 0),
            ];
        }

        return $groups;
    }

    public function availability(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'availability'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->orderBy('id')
            ->get(['id', 'teacher_id', 'room_id', 'section_id', 'workshop_id', 'day_of_week', 'period_id', 'week_no', 'kind'])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'teacher_id' => $r->teacher_id !== null ? (int) $r->teacher_id : null,
                'room_id' => $r->room_id !== null ? (int) $r->room_id : null,
                'section_id' => $r->section_id !== null ? (int) $r->section_id : null,
                'workshop_id' => $r->workshop_id !== null ? (int) $r->workshop_id : null,
                'day' => (int) $r->day_of_week,
                'period_id' => (int) $r->period_id,
                'week_no' => $r->week_no !== null ? (int) $r->week_no : null,
                'kind' => (int) $r->kind,
            ])->all();
    }

    public function rules(int $schoolId, int $academicYearId, string $onDate): array
    {
        $this->bindSchool($schoolId);
        $scopes = ['branch_id', 'department_id', 'grade_level_id', 'class_id', 'section_id', 'teacher_id', 'subject_id', 'room_id', 'activity_id', 'other_activity_id'];

        return DB::table(SchemaHelper::qualified('timetable', 'constraint_rules'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->where('effective_from', '<=', $onDate)
            ->where(static fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $onDate))
            ->orderBy('id')
            ->get(['id', 'rule_type', 'priority', 'params', 'reason', 'created_at', ...$scopes])
            ->map(static function (object $r) use ($scopes): array {
                $scope = [];
                foreach ($scopes as $column) {
                    $scope[$column] = $r->{$column} !== null ? (int) $r->{$column} : null;
                }

                return [
                    'id' => (int) $r->id,
                    'rule_type' => (string) $r->rule_type,
                    'priority' => (int) $r->priority,
                    'scope' => $scope,
                    'params' => (array) json_decode((string) $r->params, true),
                    'reason' => $r->reason,
                    'created_at' => (string) $r->created_at,
                ];
            })->all();
    }

    public function rooms(int $schoolId): array
    {
        $rooms = [];
        foreach (DB::table(SchemaHelper::qualified('organization', 'rooms').' as r')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)
            ->where('r.status', self::ACTIVE)
            ->orderBy('r.code')->orderBy('r.id')
            ->get(['r.id', 'r.code', 'r.name', 'r.capacity', 'r.room_type']) as $r) {
            $rooms[(int) $r->id] = [
                'id' => (int) $r->id,
                'code' => (string) $r->code,
                'name' => (string) $r->name,
                'capacity' => $r->capacity !== null ? (int) $r->capacity : null,
                'room_type' => $r->room_type !== null ? (int) $r->room_type : null,
            ];
        }

        return $rooms;
    }

    public function workshops(int $schoolId): array
    {
        $this->bindSchool($schoolId);
        $workshops = [];
        foreach (DB::table(SchemaHelper::qualified('vocational', 'workshops'))
            ->where('school_id', $schoolId)
            ->where('status', self::ACTIVE)
            ->orderBy('code')->orderBy('id')
            ->get(['id', 'code', 'name', 'capacity', 'safety_capacity', 'room_id']) as $w) {
            $workshops[(int) $w->id] = [
                'id' => (int) $w->id,
                'code' => (string) $w->code,
                'name' => (string) $w->name,
                'capacity' => (int) $w->capacity,
                'safety_capacity' => (int) $w->safety_capacity,
                'room_id' => $w->room_id !== null ? (int) $w->room_id : null,
            ];
        }

        return $workshops;
    }

    public function sectionInfo(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);
        $info = [];
        foreach (DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->where('s.status', self::ACTIVE)
            ->get(['s.id', 's.class_id', 'c.grade_level_id']) as $s) {
            $info[(int) $s->id] = [
                'class_id' => (int) $s->class_id,
                'grade_level_id' => $s->grade_level_id !== null ? (int) $s->grade_level_id : null,
                'branch_ids' => [],
                'department_ids' => [],
                'students' => 0,
            ];
        }
        foreach (DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->whereNotNull('section_id')
            ->groupBy('section_id', 'branch_id', 'department_id')
            ->selectRaw('section_id, branch_id, department_id, COUNT(*) AS students')
            ->get() as $e) {
            $id = (int) $e->section_id;
            if (! isset($info[$id])) {
                continue;
            }
            $info[$id]['students'] += (int) $e->students;
            if ($e->branch_id !== null && ! in_array((int) $e->branch_id, $info[$id]['branch_ids'], true)) {
                $info[$id]['branch_ids'][] = (int) $e->branch_id;
            }
            if ($e->department_id !== null && ! in_array((int) $e->department_id, $info[$id]['department_ids'], true)) {
                $info[$id]['department_ids'][] = (int) $e->department_id;
            }
        }

        return $info;
    }

    public function groupsOfEnrollment(int $schoolId, int $enrollmentId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'group_members'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('status', self::ACTIVE)
            ->orderBy('group_id')
            ->pluck('group_id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function sectionEnrollmentIds(int $schoolId, int $academicYearId, int $sectionId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'e.student_id')
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->where('e.section_id', $sectionId)
            ->where('e.status', self::ACTIVE)
            ->orderBy('st.full_name')->orderBy('e.id')
            ->pluck('e.id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function activeEnrollmentOfStudent(int $schoolId, int $academicYearId, int $studentId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('student_id', $studentId)
            ->where('status', self::ACTIVE)
            ->whereNotNull('section_id')
            ->orderByDesc('id')
            ->first(['id', 'section_id', 'academic_year_id', 'student_id']);

        return $row === null ? null : [
            'id' => (int) $row->id,
            'section_id' => (int) $row->section_id,
            'academic_year_id' => (int) $row->academic_year_id,
            'student_id' => (int) $row->student_id,
        ];
    }

    public function academicYearStart(int $academicYearId): ?string
    {
        $start = DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('id', $academicYearId)->value('start_date');

        return $start !== null ? substr((string) $start, 0, 10) : null;
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
