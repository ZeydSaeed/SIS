<?php

namespace App\Infrastructure\Persistence\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Data\CurriculumSnapshot;
use App\Domain\Curriculum\Data\CurriculumSubjectSnapshot;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\CurriculumStatus;
use Illuminate\Support\Facades\DB;

final class EloquentCurriculumRepository implements CurriculumRepositoryInterface
{
    private const COLUMNS = [
        'id', 'school_id', 'academic_year_id', 'grade_level_id', 'specialization_id', 'department_id', 'name', 'status',
        'branch_id', 'class_id', 'section_id', 'planned_subject_count',
    ];

    public function gradeLevelExists(int $gradeLevelId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('id', $gradeLevelId)
            ->where('status', 1)
            ->exists();
    }

    public function academicYearExists(int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('id', $academicYearId)
            ->exists();
    }

    public function activeCurriculumExists(int $schoolId, int $academicYearId, int $gradeLevelId, int $departmentId, ?int $exceptCurriculumId = null): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('grade_level_id', $gradeLevelId)
            ->where('department_id', $departmentId)
            ->where('status', CurriculumStatus::Active->value)
            ->when($exceptCurriculumId !== null, fn ($q) => $q->where('id', '<>', $exceptCurriculumId))
            ->exists();
    }

    /** The active vocational specialization that mirrors the department (1:1), or null. */
    private function specializationOfDepartment(int $schoolId, ?int $departmentId): ?int
    {
        if ($departmentId === null) {
            return null;
        }
        $id = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('school_id', $schoolId)->where('department_id', $departmentId)->where('status', 1)
            ->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function departmentActiveInSchool(int $schoolId, int $departmentId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('id', $departmentId)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->exists();
    }

    private function branchOfDepartment(?int $departmentId): ?int
    {
        if ($departmentId === null) {
            return null;
        }
        $id = DB::table(SchemaHelper::qualified('organization', 'departments'))->where('id', $departmentId)->value('branch_id');

        return $id === null ? null : (int) $id;
    }

    public function branchActiveInSchool(int $schoolId, int $branchId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('id', $branchId)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->exists();
    }

    public function departmentActiveInBranch(int $departmentId, int $branchId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('id', $departmentId)
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->exists();
    }

    public function activeClassGradeLevel(int $schoolId, int $academicYearId, int $classId): ?int
    {
        $this->bindSchool($schoolId);
        $grade = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 1)
            ->value('grade_level_id');

        return $grade === null ? null : (int) $grade;
    }

    public function sectionActiveInClass(int $sectionId, int $classId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('id', $sectionId)
            ->where('class_id', $classId)
            ->where('status', 1)
            ->exists();
    }

    public function activeScopeExists(
        int $schoolId,
        int $academicYearId,
        int $branchId,
        ?int $departmentId,
        int $sectionId,
        ?int $exceptCurriculumId = null,
    ): bool {
        $this->bindSchool($schoolId);

        // Same predicate as the partial unique index curricula_active_scope_unique.
        return DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('branch_id', $branchId)
            ->whereRaw('COALESCE(department_id, 0) = ?', [$departmentId ?? 0])
            ->where('section_id', $sectionId)
            ->where('status', CurriculumStatus::Active->value)
            ->when($exceptCurriculumId !== null, fn ($q) => $q->where('id', '<>', $exceptCurriculumId))
            ->exists();
    }

    public function activeClassScopeExists(
        int $schoolId,
        int $academicYearId,
        int $branchId,
        ?int $departmentId,
        int $classId,
        ?int $exceptCurriculumId = null,
    ): bool {
        $this->bindSchool($schoolId);

        // Same predicate as the partial unique index curricula_active_class_scope_unique.
        return DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('branch_id', $branchId)
            ->whereRaw('COALESCE(department_id, 0) = ?', [$departmentId ?? 0])
            ->where('class_id', $classId)
            ->whereNull('section_id')
            ->where('status', CurriculumStatus::Active->value)
            ->when($exceptCurriculumId !== null, fn ($q) => $q->where('id', '<>', $exceptCurriculumId))
            ->exists();
    }

    public function hasLineTeachers(int $schoolId, int $curriculumId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments').' as ta')
            ->join(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs', 'cs.id', '=', 'ta.curriculum_subject_id')
            ->where('cs.curriculum_id', $curriculumId)
            ->where('ta.school_id', $schoolId)
            ->exists();
    }

    public function lineStats(int $schoolId, array $curriculumIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $curriculumIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $this->bindSchool($schoolId);

        // A line is staffed when it has a main teacher and, for every part of the subject (each of its types) and every
        // section it is taught in — the curriculum's own section, else each active section of its class (none: the
        // class as a whole) — some active main teacher covers both: his part is that part or the whole subject
        // (subject_type NULL), his section is that section or all sections (section_id NULL).
        $main = "SELECT 1 FROM teachers.teaching_assignments ta WHERE ta.curriculum_subject_id = cs.id AND ta.school_id = ?
            AND ta.status = 1 AND ta.role = 1";
        $sections = "SELECT c.section_id AS id WHERE c.section_id IS NOT NULL
            UNION ALL SELECT s.id FROM enrollment.sections s WHERE c.section_id IS NULL AND s.class_id = c.class_id AND s.status = 1
            UNION ALL SELECT NULL WHERE c.section_id IS NULL
                AND NOT EXISTS (SELECT 1 FROM enrollment.sections s2 WHERE s2.class_id = c.class_id AND s2.status = 1)";
        $staffed = "COUNT(*) FILTER (WHERE EXISTS ({$main}) AND NOT EXISTS (
                SELECT 1 FROM curriculum.subject_types st CROSS JOIN LATERAL ({$sections}) sec
                WHERE st.subject_id = cs.subject_id
                AND NOT EXISTS ({$main} AND (ta.subject_type IS NULL OR ta.subject_type = st.subject_type)
                    AND (ta.section_id IS NULL OR ta.section_id = sec.id)))) AS staffed";

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->whereIn('cs.curriculum_id', $ids)
            ->where('cs.status', 1)
            ->groupBy('cs.curriculum_id')
            ->select([
                'cs.curriculum_id',
                DB::raw('COUNT(*) AS subjects'),
                DB::raw('COALESCE(SUM(cs.weekly_hours), 0) AS hours'),
            ])
            ->selectRaw($staffed, [$schoolId, $schoolId])
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->curriculum_id] = [
                'subjects' => (int) $row->subjects,
                'hours' => (int) $row->hours,
                'staffed' => (int) $row->staffed,
            ];
        }

        return $stats;
    }

    public function create(
        int $schoolId,
        int $academicYearId,
        int $gradeLevelId,
        string $name,
        ?int $specializationId,
        string $createdAt,
        ?int $departmentId = null,
        ?int $branchId = null,
        ?int $classId = null,
        ?int $sectionId = null,
        ?int $plannedSubjectCount = null,
    ): int {
        $this->bindSchool($schoolId);
        // The department is the single source; the specialization is its 1:1 mirror, filled unless one is given.
        $specializationId ??= $this->specializationOfDepartment($schoolId, $departmentId);

        return (int) DB::table(SchemaHelper::qualified('curriculum', 'curricula'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'grade_level_id' => $gradeLevelId,
            'specialization_id' => $specializationId,
            'department_id' => $departmentId,
            // The branch is the department's when only the department is given (API callers).
            'branch_id' => $branchId ?? $this->branchOfDepartment($departmentId),
            'class_id' => $classId,
            'section_id' => $sectionId,
            'planned_subject_count' => $plannedSubjectCount,
            'name' => $name,
            'status' => CurriculumStatus::Active->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function findActiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Active->value)
            ->first(self::COLUMNS);

        return $row === null ? null : $this->mapCurriculum($row);
    }

    public function findInactiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Inactive->value)
            ->first(self::COLUMNS);

        return $row === null ? null : $this->mapCurriculum($row);
    }

    public function listActiveForSchool(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', CurriculumStatus::Active->value)
            ->orderBy('id')
            ->get(self::COLUMNS);

        return $rows->map(fn ($row): CurriculumSnapshot => $this->mapCurriculum($row))->all();
    }

    public function listForSchool(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CurriculumStatus::Active->value])
            ->orderBy('id')
            ->get(self::COLUMNS);

        return $rows->map(fn ($row): CurriculumSnapshot => $this->mapCurriculum($row))->all();
    }

    public function searchForSchool(
        int $schoolId,
        ?int $academicYearId,
        ?int $status,
        int|array|null $gradeLevelId,
        int|array|null $specializationId,
        int|array|null $branchId,
        string $q,
        int $page,
        int $perPage,
        int|array|null $departmentId = null,
    ): array {
        $this->bindSchool($schoolId);

        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        $departments = SchemaHelper::qualified('organization', 'departments');

        // الاختصاص = the branch's department (curricula.department_id).
        $query = DB::table($curricula.' as c')
            ->leftJoin($departments.' as d', 'd.id', '=', 'c.department_id')
            ->where('c.school_id', $schoolId);

        if ($academicYearId !== null) {
            $query->where('c.academic_year_id', $academicYearId);
        }

        if ($status !== null) {
            $query->where('c.status', $status);
        }
        // Each of these filters takes one id or several (a multi-choice ribbon filter).
        foreach ([
            'c.grade_level_id' => $gradeLevelId,
            'c.specialization_id' => $specializationId,
            'c.department_id' => $departmentId,
            'd.branch_id' => $branchId,
        ] as $column => $value) {
            $ids = array_values(array_filter(array_map('intval', $value === null ? [] : (array) $value), static fn (int $id): bool => $id > 0));
            if ($ids !== []) {
                $query->whereIn($column, $ids);
            }
        }

        $needle = trim($q);
        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('c.name', 'ilike', $like)
                    ->orWhere('d.name', 'ilike', $like);
            });
        }

        $total = (int) (clone $query)->count('c.id');
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $rows = $query
            ->orderByRaw('CASE WHEN c.status = ? THEN 0 ELSE 1 END', [CurriculumStatus::Active->value])
            ->orderBy('c.academic_year_id')
            ->orderBy('c.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'c.id', 'c.school_id', 'c.academic_year_id', 'c.grade_level_id',
                'c.specialization_id', 'c.department_id', 'c.name', 'c.status',
                'c.branch_id', 'c.class_id', 'c.section_id', 'c.planned_subject_count',
            ]);

        return [
            'items' => $rows->map(fn ($row): CurriculumSnapshot => $this->mapCurriculum($row))->all(),
            'total' => $total,
        ];
    }

    public function deactivate(int $schoolId, int $curriculumId): bool
    {
        $this->bindSchool($schoolId);

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Active->value)
            ->update([
                'status' => CurriculumStatus::Inactive->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function reactivate(int $schoolId, int $curriculumId): bool
    {
        $this->bindSchool($schoolId);

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Inactive->value)
            ->update([
                'status' => CurriculumStatus::Active->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function updateSpecialization(
        int $schoolId,
        int $curriculumId,
        ?int $specializationId,
        string $updatedAt,
    ): bool {
        return $this->updateActive($schoolId, $curriculumId, [
            'specialization_id' => $specializationId,
        ], $updatedAt);
    }

    public function updateActive(
        int $schoolId,
        int $curriculumId,
        array $fields,
        string $updatedAt,
    ): bool {
        $this->bindSchool($schoolId);

        $payload = ['updated_at' => $updatedAt];
        if (array_key_exists('name', $fields)) {
            $payload['name'] = $fields['name'];
        }
        if (array_key_exists('specialization_id', $fields)) {
            $payload['specialization_id'] = $fields['specialization_id'];
        }
        if (array_key_exists('planned_subject_count', $fields)) {
            $payload['planned_subject_count'] = $fields['planned_subject_count'];
        }
        if (array_key_exists('department_id', $fields)) {
            $payload['department_id'] = $fields['department_id'];
            if (! array_key_exists('specialization_id', $fields)) {
                $payload['specialization_id'] = $this->specializationOfDepartment($schoolId, $fields['department_id'] !== null ? (int) $fields['department_id'] : null);
            }
        }
        if (count($payload) === 1) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Active->value)
            ->update($payload);

        return $updated > 0;
    }

    public function linkSubject(
        int $schoolId,
        int $curriculumId,
        int $subjectId,
        ?int $weeklyHours,
        bool $isRequired,
        int $subjectOrder,
        string $createdAt,
        ?int $theoryHours = null,
        ?int $practicalHours = null,
    ): int {
        $this->bindSchool($schoolId);
        $weeklyHours = self::totalHours($weeklyHours, $theoryHours, $practicalHours);

        $existing = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->where('subject_id', $subjectId)
            ->first(['id', 'status']);

        if ($existing !== null) {
            if ((int) $existing->status !== CurriculumStatus::Active->value) {
                DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
                    ->where('id', (int) $existing->id)
                    ->update([
                        'status' => CurriculumStatus::Active->value,
                        'weekly_hours' => $weeklyHours,
                        'theory_hours' => $theoryHours,
                        'practical_hours' => $practicalHours,
                        'is_required' => $isRequired,
                        'subject_order' => $subjectOrder,
                    ]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->insertGetId([
            'curriculum_id' => $curriculumId,
            'subject_id' => $subjectId,
            'weekly_hours' => $weeklyHours,
            'theory_hours' => $theoryHours,
            'practical_hours' => $practicalHours,
            'is_required' => $isRequired,
            'subject_order' => $subjectOrder,
            'status' => CurriculumStatus::Active->value,
            'created_at' => $createdAt,
        ]);
    }

    public function updateLinkHours(int $schoolId, int $linkId, ?int $theoryHours, ?int $practicalHours): bool
    {
        $this->bindSchool($schoolId);
        if ($this->findActiveLink($schoolId, $linkId) === null) {
            return false;
        }

        return DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('id', $linkId)
            ->where('status', CurriculumStatus::Active->value)
            ->update([
                'theory_hours' => $theoryHours,
                'practical_hours' => $practicalHours,
                'weekly_hours' => self::totalHours(null, $theoryHours, $practicalHours),
            ]) > 0;
    }

    /** The timetable reads weekly_hours: with a «نظري / عملي» split it is their sum (0 → no hours). */
    private static function totalHours(?int $weeklyHours, ?int $theoryHours, ?int $practicalHours): ?int
    {
        if ($theoryHours === null && $practicalHours === null) {
            return $weeklyHours;
        }
        $sum = ($theoryHours ?? 0) + ($practicalHours ?? 0);

        return $sum > 0 ? $sum : null;
    }

    public function findActiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('cs.id', $linkId)
            ->where('c.school_id', $schoolId)
            ->where('cs.status', CurriculumStatus::Active->value)
            ->first([
                'cs.id', 'cs.curriculum_id', 'cs.subject_id', 'cs.weekly_hours', 'cs.theory_hours', 'cs.practical_hours',
                'cs.is_required', 'cs.subject_order', 'cs.status',
            ]);

        return $row === null ? null : $this->mapLink($row);
    }

    public function findInactiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('cs.id', $linkId)
            ->where('c.school_id', $schoolId)
            ->where('cs.status', CurriculumStatus::Inactive->value)
            ->first([
                'cs.id', 'cs.curriculum_id', 'cs.subject_id', 'cs.weekly_hours', 'cs.theory_hours', 'cs.practical_hours',
                'cs.is_required', 'cs.subject_order', 'cs.status',
            ]);

        return $row === null ? null : $this->mapLink($row);
    }

    public function listActiveLinks(int $schoolId, int $curriculumId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->where('status', CurriculumStatus::Active->value)
            ->orderBy('subject_order')
            ->orderBy('id')
            ->get([
                'id', 'curriculum_id', 'subject_id', 'weekly_hours', 'theory_hours', 'practical_hours',
                'is_required', 'subject_order', 'status',
            ]);

        return $rows->map(fn ($row): CurriculumSubjectSnapshot => $this->mapLink($row))->all();
    }

    public function listLinks(int $schoolId, int $curriculumId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->orderBy('subject_order')
            ->orderBy('id')
            ->get([
                'id', 'curriculum_id', 'subject_id', 'weekly_hours', 'theory_hours', 'practical_hours',
                'is_required', 'subject_order', 'status',
            ]);

        return $rows->map(fn ($row): CurriculumSubjectSnapshot => $this->mapLink($row))->all();
    }

    public function curriculumNamesBySubject(int $schoolId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('c.school_id', $schoolId)
            ->orderBy('c.name')
            ->get(['cs.subject_id', 'c.name']);

        $names = [];
        foreach ($rows as $row) {
            $subjectId = (int) $row->subject_id;
            $name = (string) $row->name;
            if (! in_array($name, $names[$subjectId] ?? [], true)) {
                $names[$subjectId][] = $name;
            }
        }

        return $names;
    }

    public function deactivateLink(int $schoolId, int $linkId): bool
    {
        $this->bindSchool($schoolId);

        $link = $this->findActiveLink($schoolId, $linkId);
        if ($link === null) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('id', $linkId)
            ->where('status', CurriculumStatus::Active->value)
            ->update(['status' => CurriculumStatus::Inactive->value]);

        return $updated > 0;
    }

    public function reactivateLink(int $schoolId, int $linkId): bool
    {
        $this->bindSchool($schoolId);

        $link = $this->findInactiveLink($schoolId, $linkId);
        if ($link === null) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('id', $linkId)
            ->where('status', CurriculumStatus::Inactive->value)
            ->update(['status' => CurriculumStatus::Active->value]);

        return $updated > 0;
    }

    private function mapCurriculum(object $row): CurriculumSnapshot
    {
        return new CurriculumSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            gradeLevelId: (int) $row->grade_level_id,
            specializationId: $row->specialization_id !== null ? (int) $row->specialization_id : null,
            name: (string) $row->name,
            status: (int) $row->status,
            departmentId: isset($row->department_id) ? (int) $row->department_id : null,
            branchId: isset($row->branch_id) ? (int) $row->branch_id : null,
            classId: isset($row->class_id) ? (int) $row->class_id : null,
            sectionId: isset($row->section_id) ? (int) $row->section_id : null,
            plannedSubjectCount: isset($row->planned_subject_count) ? (int) $row->planned_subject_count : null,
        );
    }

    private function mapLink(object $row): CurriculumSubjectSnapshot
    {
        return new CurriculumSubjectSnapshot(
            id: (int) $row->id,
            curriculumId: (int) $row->curriculum_id,
            subjectId: (int) $row->subject_id,
            weeklyHours: $row->weekly_hours !== null ? (int) $row->weekly_hours : null,
            isRequired: (bool) $row->is_required,
            subjectOrder: (int) $row->subject_order,
            status: (int) $row->status,
            theoryHours: isset($row->theory_hours) ? (int) $row->theory_hours : null,
            practicalHours: isset($row->practical_hours) ? (int) $row->practical_hours : null,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
