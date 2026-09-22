<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\DTOs\EnrollmentDTO;
use App\Database\SchemaHelper;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentEnrollmentReadRepository implements EnrollmentReadRepositoryInterface
{
    public function findDetail(int $enrollmentId, int $schoolId): ?EnrollmentDTO
    {
        $row = $this->baseQuery($schoolId)
            ->where('e.id', $enrollmentId)
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toDto($row);
    }

    public function paginate(
        int $schoolId,
        ?int $academicYearId,
        int $page,
        int $perPage,
        ?int $status = null,
        string $q = '',
        ?int $gender = null,
        ?int $classId = null,
        ?int $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        ?int $branchId = null,
        ?int $departmentId = null,
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $query = $this->filteredQuery(
            $schoolId,
            $academicYearId,
            $status,
            $q,
            $gender,
            $classId,
            $sectionId,
            $departmentName,
            $specializationId,
            $branchId,
            $departmentId,
        );
        $total = (clone $query)->count('e.id');
        $rows = $query
            ->orderBy('s.full_name')
            ->orderBy('e.id')
            ->forPage($page, $perPage)
            ->get();

        /** @var list<EnrollmentDTO> $items */
        $items = $rows
            ->map(fn (object $row): EnrollmentDTO => $this->toDto($row))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function countByStatus(
        int $schoolId,
        ?int $academicYearId = null,
        ?int $gender = null,
        ?int $classId = null,
        ?int $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        ?int $branchId = null,
        ?int $departmentId = null,
    ): array {
        $rows = $this->filteredQuery(
            $schoolId,
            $academicYearId,
            null,
            '',
            $gender,
            $classId,
            $sectionId,
            $departmentName,
            $specializationId,
            $branchId,
            $departmentId,
        )
            ->select('e.status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('e.status')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    public function listFilterOptions(int $schoolId, ?int $academicYearId, ?int $classId = null): array
    {
        $classesTable = SchemaHelper::qualified('enrollment', 'classes');
        $sectionsTable = SchemaHelper::qualified('enrollment', 'sections');
        $specializationsTable = SchemaHelper::qualified('vocational', 'specializations');
        $branchesTable = SchemaHelper::qualified('organization', 'branches');
        $departmentsTable = SchemaHelper::qualified('organization', 'departments');

        $classesQuery = DB::table($classesTable)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('id')
            ->select(['id', 'code', 'name', 'grade_level_id']);
        if ($academicYearId !== null) {
            $classesQuery->where('academic_year_id', $academicYearId);
        }
        $classes = $classesQuery->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'grade_level_id' => (int) $row->grade_level_id,
            ])
            ->values()
            ->all();

        $sectionsQuery = DB::table($sectionsTable)
            ->where('status', 1)
            ->orderBy('name')
            ->select(['id', 'class_id', 'code', 'name']);
        if ($classId !== null) {
            $sectionsQuery->where('class_id', $classId);
        } else {
            $classIds = array_map(static fn (array $row): int => $row['id'], $classes);
            if ($classIds === []) {
                $sectionsQuery->whereRaw('1 = 0');
            } else {
                $sectionsQuery->whereIn('class_id', $classIds);
            }
        }
        $sections = $sectionsQuery->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'class_id' => (int) $row->class_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();

        $branches = DB::table($branchesTable)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->select(['id', 'code', 'name'])
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();

        $departments = DB::table($departmentsTable)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->select(['id', 'branch_id', 'code', 'name'])
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();

        $specializations = DB::table($specializationsTable)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->select(['id', 'department_id', 'code', 'name'])
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();

        $gradeLevelsTable = SchemaHelper::qualified('academic', 'grade_levels');
        $gradeLevels = DB::table($gradeLevelsTable)
            ->where('status', 1)
            ->orderBy('level_order')
            ->select(['id', 'code', 'name', 'education_stage'])
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'education_stage' => (int) $row->education_stage,
            ])
            ->values()
            ->all();

        return [
            'branches' => $branches,
            'classes' => $classes,
            'sections' => $sections,
            'departments' => $departments,
            'specializations' => $specializations,
            'grade_levels' => $gradeLevels,
        ];
    }

    private function filteredQuery(
        int $schoolId,
        ?int $academicYearId,
        ?int $status,
        string $q,
        ?int $gender,
        ?int $classId = null,
        ?int $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        ?int $branchId = null,
        ?int $departmentId = null,
    ): Builder {
        $query = $this->baseQuery($schoolId);

        if ($academicYearId !== null) {
            $query->where('e.academic_year_id', $academicYearId);
        }

        if ($status !== null && in_array($status, EnrollmentStatus::all(), true)) {
            $query->where('e.status', $status);
        }

        if ($gender === 1 || $gender === 2) {
            $query->where('s.gender', $gender);
        }

        if ($classId !== null && $classId > 0) {
            $query->where('e.class_id', $classId);
        }

        if ($sectionId !== null && $sectionId > 0) {
            $query->where('e.section_id', $sectionId);
        }

        if ($branchId !== null && $branchId > 0) {
            $query->where(function (Builder $builder) use ($branchId): void {
                $builder->where('e.branch_id', $branchId)
                    ->orWhere('s.branch_id', $branchId);
            });
        }

        if ($departmentId !== null && $departmentId > 0) {
            $departmentsTable = SchemaHelper::qualified('organization', 'departments');
            $departmentNameFromId = DB::table($departmentsTable)
                ->where('id', $departmentId)
                ->value('name');
            $departmentNameFromId = is_string($departmentNameFromId)
                ? trim($departmentNameFromId)
                : '';

            $query->where(function (Builder $builder) use ($departmentId, $departmentNameFromId): void {
                $builder->where('e.department_id', $departmentId);
                if ($departmentNameFromId !== '') {
                    $builder->orWhere('s.department_name', $departmentNameFromId);
                }
            });
        } else {
            $department = trim((string) ($departmentName ?? ''));
            if ($department !== '') {
                $query->where('s.department_name', $department);
            }
        }

        if ($specializationId !== null && $specializationId > 0) {
            $specializationsTable = SchemaHelper::qualified('vocational', 'specializations');
            $spec = DB::table($specializationsTable)
                ->where('id', $specializationId)
                ->select(['id', 'name'])
                ->first();

            if ($spec === null) {
                $query->whereRaw('1 = 0');
            } else {
                $specName = trim((string) $spec->name);
                $query->where(function (Builder $builder) use ($specializationId, $specName): void {
                    $builder->where('e.specialization_id', $specializationId);
                    if ($specName !== '') {
                        $builder->orWhere('s.specialization_name', $specName);
                    }
                });
            }
        }

        $term = trim($q);
        if ($term !== '') {
            $likeOperator = SchemaHelper::isPostgreSql() ? 'ilike' : 'like';
            $pattern = '%'.$term.'%';

            $query->where(function (Builder $builder) use ($likeOperator, $pattern, $term): void {
                $builder->where('e.enrollment_number', $likeOperator, $pattern)
                    ->orWhere('s.full_name', $likeOperator, $pattern)
                    ->orWhere('s.student_code', $likeOperator, $pattern)
                    ->orWhere('s.first_name', $likeOperator, $pattern)
                    ->orWhere('s.father_name', $likeOperator, $pattern)
                    ->orWhere('s.grandfather_name', $likeOperator, $pattern)
                    ->orWhere('s.great_grandfather_name', $likeOperator, $pattern)
                    ->orWhere('s.last_name', $likeOperator, $pattern)
                    ->orWhere('c.code', $likeOperator, $pattern)
                    ->orWhere('c.name', $likeOperator, $pattern)
                    ->orWhere('sec.code', $likeOperator, $pattern)
                    ->orWhere('sec.name', $likeOperator, $pattern)
                    ->orWhere('sp.code', $likeOperator, $pattern)
                    ->orWhere('sp.name', $likeOperator, $pattern)
                    ->orWhere('g.name', $likeOperator, $pattern)
                    ->orWhere('y.name', $likeOperator, $pattern)
                    ->orWhere('y.code', $likeOperator, $pattern)
                    ->orWhere('br.name', $likeOperator, $pattern)
                    ->orWhere('dep.name', $likeOperator, $pattern);

                if (ctype_digit($term)) {
                    $studentId = (int) $term;
                    $builder->orWhere('s.id', $studentId)
                        ->orWhere('e.student_id', $studentId);
                }
            });
        }

        return $query;
    }

    private function baseQuery(int $schoolId): Builder
    {
        $enrollments = SchemaHelper::qualified('enrollment', 'enrollments');
        $students = SchemaHelper::qualified('students', 'students');
        $years = SchemaHelper::qualified('academic', 'academic_years');
        $classes = SchemaHelper::qualified('enrollment', 'classes');
        $sections = SchemaHelper::qualified('enrollment', 'sections');
        $gradeLevels = SchemaHelper::qualified('academic', 'grade_levels');
        $specializations = SchemaHelper::qualified('vocational', 'specializations');
        $schools = SchemaHelper::qualified('organization', 'schools');
        $branches = SchemaHelper::qualified('organization', 'branches');
        $departments = SchemaHelper::qualified('organization', 'departments');

        return DB::table($enrollments.' as e')
            ->join($students.' as s', 's.id', '=', 'e.student_id')
            ->join($years.' as y', 'y.id', '=', 'e.academic_year_id')
            ->join($classes.' as c', 'c.id', '=', 'e.class_id')
            ->join($sections.' as sec', 'sec.id', '=', 'e.section_id')
            ->leftJoin($gradeLevels.' as g', 'g.id', '=', 'c.grade_level_id')
            ->leftJoin($specializations.' as sp', 'sp.id', '=', 'e.specialization_id')
            ->leftJoin($schools.' as sch', 'sch.id', '=', 'e.school_id')
            ->leftJoin($branches.' as br', 'br.id', '=', 'e.branch_id')
            ->leftJoin($departments.' as dep', 'dep.id', '=', 'e.department_id')
            ->where('e.school_id', $schoolId)
            ->select([
                'e.id',
                'e.student_id',
                'e.school_id',
                'e.academic_year_id',
                'e.branch_id',
                'e.department_id',
                'e.class_id',
                'e.section_id',
                'e.specialization_id',
                'e.enrollment_number',
                'e.status',
                'e.effective_from',
                'e.effective_to',
                'e.enrolled_by',
                'e.created_at',
                'e.updated_at',
                's.student_code',
                's.full_name as student_full_name',
                's.first_name as student_first_name',
                's.father_name as student_father_name',
                's.grandfather_name as student_grandfather_name',
                's.great_grandfather_name as student_great_grandfather_name',
                's.last_name as student_last_name',
                's.gender as student_gender',
                's.birth_date as student_birth_date',
                's.status as student_status',
                's.school_name as student_school_name',
                's.department_name',
                's.stage_name as student_stage_name',
                's.specialization_name as student_specialization_name',
                'y.name as academic_year_name',
                'y.code as academic_year_code',
                'c.code as class_code',
                'c.name as class_name',
                'sec.code as section_code',
                'sec.name as section_name',
                'sp.code as specialization_code',
                'sp.name as specialization_name',
                'g.code as grade_level_code',
                'g.name as grade_level_name',
                'sch.name as school_name',
                'br.name as branch_name',
                'br.code as branch_code',
                'dep.name as org_department_name',
                'dep.code as department_code',
            ]);
    }

    private function toDto(object $row): EnrollmentDTO
    {
        $specializationName = $this->nullableString($row->specialization_name ?? null)
            ?? $this->nullableString($row->student_specialization_name ?? null);
        $schoolName = $this->nullableString($row->school_name ?? null)
            ?? $this->nullableString($row->student_school_name ?? null);

        return new EnrollmentDTO(
            id: (int) $row->id,
            studentId: (int) $row->student_id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            classId: (int) $row->class_id,
            sectionId: (int) $row->section_id,
            enrollmentNumber: (string) $row->enrollment_number,
            status: (int) $row->status,
            effectiveFrom: $this->formatDate($row->effective_from) ?? '',
            effectiveTo: $this->formatDate($row->effective_to ?? null),
            specializationId: $this->nullableInt($row->specialization_id ?? null),
            branchId: $this->nullableInt($row->branch_id ?? null),
            departmentId: $this->nullableInt($row->department_id ?? null),
            enrolledBy: $this->nullableInt($row->enrolled_by ?? null),
            studentCode: $this->nullableString($row->student_code ?? null),
            studentFullName: $this->nullableString($row->student_full_name ?? null),
            studentFirstName: $this->nullableString($row->student_first_name ?? null),
            studentFatherName: $this->nullableString($row->student_father_name ?? null),
            studentGrandfatherName: $this->nullableString($row->student_grandfather_name ?? null),
            studentGreatGrandfatherName: $this->nullableString($row->student_great_grandfather_name ?? null),
            studentLastName: $this->nullableString($row->student_last_name ?? null),
            studentGender: $this->nullableInt($row->student_gender ?? null),
            studentBirthDate: $this->formatDate($row->student_birth_date ?? null),
            studentStatus: $this->nullableInt($row->student_status ?? null),
            schoolName: $schoolName,
            academicYearName: $this->nullableString($row->academic_year_name ?? null),
            academicYearCode: $this->nullableString($row->academic_year_code ?? null),
            classCode: $this->nullableString($row->class_code ?? null),
            className: $this->nullableString($row->class_name ?? null),
            sectionCode: $this->nullableString($row->section_code ?? null),
            sectionName: $this->nullableString($row->section_name ?? null),
            specializationCode: $this->nullableString($row->specialization_code ?? null),
            specializationName: $specializationName,
            branchCode: $this->nullableString($row->branch_code ?? null),
            branchName: $this->nullableString($row->branch_name ?? null),
            gradeLevelCode: $this->nullableString($row->grade_level_code ?? null),
            gradeLevelName: $this->nullableString($row->grade_level_name ?? null),
            departmentName: $this->nullableString($row->org_department_name ?? null)
                ?? $this->nullableString($row->department_name ?? null),
            stageName: $this->nullableString($row->student_stage_name ?? null),
            createdAt: $this->formatTimestamp($row->created_at ?? null),
            updatedAt: $this->formatTimestamp($row->updated_at ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
