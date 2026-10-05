<?php

namespace App\Infrastructure\Persistence\Student;

use App\Application\Student\Contracts\StudentReadRepositoryInterface;
use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;
use App\Database\SchemaHelper;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Student\ValueObjects\StudentReligion;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentStudentManagementReadRepository implements StudentReadRepositoryInterface
{
    /**
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id',
        'student_code',
        'full_name',
        'first_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'last_name',
        'mother_name',
        'maternal_father_name',
        'maternal_grandfather_name',
        'guardian_triple_name',
        'governorate',
        'neighborhood',
        'locality',
        'house_number',
        'birth_date',
        'birth_place',
        'registration_place',
        'gender',
        'nationality',
        'religion',
        'mawalid_date',
        'national_id',
        'previous_school_name',
        'transfer_document_number',
        'transfer_document_date',
        'school_start_date',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'branch_id',
        'department_id',
        'grade_level_id',

        'father_occupation',
        'mother_occupation',
        'administrative_unit',
        'graduation_year',
        'previous_gpa',
        'previous_study_track',
        'mathematics_grade',
        'physics_grade',
        'request_kind',
        'admitted_academic_year_id',
        'status',
    ];

    /**
     * @var list<string>
     */
    private const DETAIL_COLUMNS = [
        'id',
        'public_id',
        'student_code',
        'national_id',
        'first_name',
        'middle_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'last_name',
        'mother_name',
        'maternal_father_name',
        'maternal_grandfather_name',
        'full_name',
        'guardian_triple_name',
        'gender',
        'birth_date',
        'mawalid_date',
        'birth_place',
        'nationality',
        'governorate',
        'neighborhood',
        'locality',
        'house_number',
        'registration_place',
        'religion',
        'previous_school_name',
        'transfer_document_number',
        'transfer_document_date',
        'school_start_date',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'branch_id',
        'department_id',
        'grade_level_id',

        'father_occupation',
        'mother_occupation',
        'administrative_unit',
        'graduation_year',
        'previous_gpa',
        'previous_study_track',
        'mathematics_grade',
        'physics_grade',
        'request_kind',
        'status',
        'created_at',
        'updated_at',
    ];

    public function findDetail(int $studentId, int $schoolId, ?int $academicYearId = null): ?StudentDetailDTO
    {
        $studentsTable = SchemaHelper::qualified('students', 'students');
        $branchesTable = SchemaHelper::qualified('organization', 'branches');

        $record = StudentRecord::query()
            ->from($studentsTable.' as s')
            ->leftJoin($branchesTable.' as br', 'br.id', '=', 's.branch_id')
            ->select(array_map(
                static fn (string $column): string => 's.'.$column,
                self::DETAIL_COLUMNS,
            ))
            ->addSelect('br.name as branch_name')
            ->tap(fn (Builder $query) => $this->joinPlacementNames($query))
            ->where('s.id', $studentId)
            ->where('s.school_id', $schoolId)
            ->first();

        if ($record === null) {
            return null;
        }

        $year = $this->resolveAcademicYearContext($studentId, $schoolId, $academicYearId);

        return $this->mapDetail(
            $record,
            $year,
            $this->activeEnrollmentStudentIds([$studentId], $schoolId, $year['id'])[$studentId] ?? null,
        );
    }

    public function paginate(
        ?int $status,
        int $schoolId,
        int $page,
        int $perPage,
        ?int $academicYearId = null,
        ?int $gender = null,
        ?bool $enrolled = null,
        ?int $requestKind = null,
    ): array {
        $query = $this->baseListQuery($schoolId);

        $this->applyAcademicYearScope($query, $schoolId, $academicYearId);
        $this->applyGenderScope($query, $gender);
        $this->applyEnrollmentScope($query, $schoolId, $academicYearId, $enrolled);
        $this->applyRequestKindScope($query, $requestKind);

        if ($status !== null) {
            $query->where('s.status', $status);
        }

        return $this->paginateQuery($query, $page, $perPage, $schoolId, $academicYearId);
    }

    public function search(
        string $term,
        int $schoolId,
        int $page,
        int $perPage,
        ?int $status = null,
        ?int $academicYearId = null,
        ?int $gender = null,
        ?bool $enrolled = null,
        ?int $requestKind = null,
    ): array {
        $term = trim($term);
        $query = $this->baseListQuery($schoolId);

        $this->applyAcademicYearScope($query, $schoolId, $academicYearId);
        $this->applyGenderScope($query, $gender);
        $this->applyEnrollmentScope($query, $schoolId, $academicYearId, $enrolled);
        $this->applyRequestKindScope($query, $requestKind);

        if ($status !== null) {
            $query->where('s.status', $status);
        }

        if ($term !== '') {
            $likeOperator = SchemaHelper::isPostgreSql() ? 'ilike' : 'like';

            // Exact code / id path avoids leading-wildcard ILIKE when the term is numeric.
            if (ctype_digit($term)) {
                $id = (int) $term;
                $query->where(function (Builder $builder) use ($term, $id): void {
                    $builder->where('s.student_code', $term);
                    if ($id > 0) {
                        $builder->orWhere('s.id', $id);
                    }
                });
            } else {
                $pattern = '%'.$term.'%';

                $query->where(function (Builder $builder) use ($likeOperator, $pattern): void {
                    $builder->where('s.full_name', $likeOperator, $pattern)
                        ->orWhere('s.student_code', $likeOperator, $pattern)
                        ->orWhere('s.national_id', $likeOperator, $pattern)
                        ->orWhere('s.first_name', $likeOperator, $pattern)
                        ->orWhere('s.father_name', $likeOperator, $pattern)
                        ->orWhere('s.grandfather_name', $likeOperator, $pattern)
                        ->orWhere('s.great_grandfather_name', $likeOperator, $pattern)
                        ->orWhere('s.last_name', $likeOperator, $pattern)
                        ->orWhere('s.mother_name', $likeOperator, $pattern)
                        ->orWhere('s.maternal_father_name', $likeOperator, $pattern)
                        ->orWhere('s.maternal_grandfather_name', $likeOperator, $pattern);
                });
            }
        }

        return $this->paginateQuery($query, $page, $perPage, $schoolId, $academicYearId);
    }

    /**
     * The student keeps placement as ids; the department / grade labels the UI shows
     * (department_name, admitted_class_name) are read from the catalogs.
     */
    private function joinPlacementNames(Builder $query): void
    {
        $query
            ->leftJoin(SchemaHelper::qualified('organization', 'departments').' as sdep', 'sdep.id', '=', 's.department_id')
            ->leftJoin(SchemaHelper::qualified('academic', 'grade_levels').' as sgl', 'sgl.id', '=', 's.grade_level_id')
            ->addSelect(['sdep.name as department_name', 'sgl.name as admitted_class_name']);
    }

    private function baseListQuery(int $schoolId): Builder
    {
        $studentsTable = SchemaHelper::qualified('students', 'students');
        $branchesTable = SchemaHelper::qualified('organization', 'branches');

        return StudentRecord::query()
            ->from($studentsTable.' as s')
            ->leftJoin($branchesTable.' as br', 'br.id', '=', 's.branch_id')
            ->select(array_map(
                static fn (string $column): string => 's.'.$column,
                self::LIST_COLUMNS,
            ))
            ->addSelect('br.name as branch_name')
            ->tap(fn (Builder $query) => $this->joinPlacementNames($query))
            ->where('s.school_id', $schoolId)
            ->orderBy('s.full_name')
            ->orderBy('s.id');
    }

    /**
     * @return array<int, int>
     */
    public function countByStatus(
        int $schoolId,
        ?int $academicYearId = null,
        ?int $gender = null,
        ?bool $enrolled = null,
        ?int $requestKind = null,
    ): array {
        $query = StudentRecord::query()->where('school_id', $schoolId);
        $this->applyAcademicYearScope($query, $schoolId, $academicYearId);
        $this->applyGenderScope($query, $gender);
        $this->applyEnrollmentScope($query, $schoolId, $academicYearId, $enrolled);
        $this->applyRequestKindScope($query, $requestKind);

        $rows = $query
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginateQuery(
        Builder $query,
        int $page,
        int $perPage,
        int $schoolId,
        ?int $academicYearId,
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        // Preserve join select (incl. branch_name); do not re-select LIST_COLUMNS only.
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        /** @var list<StudentRecord> $records */
        $records = array_values($paginator->items());
        $enrolledIds = $this->activeEnrollmentStudentIds(
            array_map(static fn (StudentRecord $record): int => (int) $record->getKey(), $records),
            $schoolId,
            $academicYearId,
        );

        /** @var list<StudentListItemDTO> $items */
        $items = array_map(
            fn (StudentRecord $record): StudentListItemDTO => $this->mapListItem(
                $record,
                $enrolledIds[(int) $record->getKey()] ?? null,
            ),
            $records,
        );

        $documentsByStudent = $this->activeDocumentsByStudentIds(
            $schoolId,
            array_map(static fn (StudentListItemDTO $item): int => $item->id, $items),
        );

        $items = array_map(
            static function (StudentListItemDTO $item) use ($documentsByStudent): StudentListItemDTO {
                return $item->withDocuments($documentsByStudent[$item->id] ?? []);
            },
            $items,
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, list<array{id: int, document_type: int, file_name: string}>>
     */
    private function activeDocumentsByStudentIds(int $schoolId, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            ->where('status', 1)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get(['id', 'student_id', 'document_type', 'file_name']);

        $map = [];
        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;
            $map[$studentId] ??= [];
            $map[$studentId][] = [
                'id' => (int) $row->id,
                'document_type' => (int) $row->document_type,
                'file_name' => (string) $row->file_name,
            ];
        }

        return $map;
    }

    /**
     * @param  array{id: int, grade_name: string|null}|null  $enrollment
     */
    private function mapListItem(StudentRecord $record, ?array $enrollment = null): StudentListItemDTO
    {
        return new StudentListItemDTO(
            id: (int) $record->getKey(),
            studentCode: (string) $record->student_code,
            fullName: (string) $record->full_name,
            firstName: (string) $record->first_name,
            fatherName: $record->father_name,
            grandfatherName: $record->grandfather_name,
            greatGrandfatherName: $record->great_grandfather_name,
            lastName: (string) $record->last_name,
            motherName: $record->mother_name,
            maternalFatherName: $record->maternal_father_name,
            maternalGrandfatherName: $record->maternal_grandfather_name,
            guardianTripleName: $record->guardian_triple_name,
            governorate: $record->governorate,
            neighborhood: $record->neighborhood,
            locality: $record->locality,
            houseNumber: $record->house_number,
            birthDate: $record->birth_date->format('Y-m-d'),
            birthPlace: $record->birth_place,
            registrationPlace: $record->registration_place,
            gender: (int) $record->gender,
            nationality: $record->nationality,
            religion: (int) ($record->religion ?? StudentReligion::Muslim->value),
            mawalidDate: $record->mawalid_date?->format('Y-m-d'),
            nationalId: $record->national_id,
            previousSchoolName: $record->previous_school_name,
            transferDocumentNumber: $record->transfer_document_number !== null
                ? (int) $record->transfer_document_number
                : null,
            transferDocumentDate: $record->transfer_document_date?->format('Y-m-d'),
            schoolStartDate: $record->school_start_date?->format('Y-m-d'),
            admittedClassName: $record->admitted_class_name,
            notes: $record->notes,
            mobile: $record->mobile,
            guardianMobile: $record->guardian_mobile,
            email: $record->email,
            schoolName: $record->school_name,
            branchId: $record->branch_id !== null ? (int) $record->branch_id : null,
            branchName: isset($record->branch_name) && is_string($record->branch_name) && $record->branch_name !== ''
                ? $record->branch_name
                : null,
            departmentName: $record->department_name,

            fatherOccupation: $record->father_occupation,
            motherOccupation: $record->mother_occupation,
            administrativeUnit: $record->administrative_unit !== null ? (int) $record->administrative_unit : null,
            graduationYear: $record->graduation_year !== null ? (int) $record->graduation_year : null,
            previousGpa: $record->previous_gpa !== null ? (float) $record->previous_gpa : null,
            previousStudyTrack: $record->previous_study_track !== null ? (int) $record->previous_study_track : null,
            mathematicsGrade: $record->mathematics_grade !== null ? (float) $record->mathematics_grade : null,
            physicsGrade: $record->physics_grade !== null ? (float) $record->physics_grade : null,
            requestKind: $record->request_kind !== null ? (int) $record->request_kind : null,
            academicYearId: $record->admitted_academic_year_id !== null
                ? (int) $record->admitted_academic_year_id
                : null,
            status: (int) $record->status,
            isEnrolled: $enrollment !== null,
            activeEnrollmentId: $enrollment['id'] ?? null,
            enrollmentGradeName: $enrollment['grade_name'] ?? null,
            departmentId: $record->department_id !== null ? (int) $record->department_id : null,
            gradeLevelId: $record->grade_level_id !== null ? (int) $record->grade_level_id : null,
        );
    }

    /**
     * @param  array{id: int|null, name: string|null, code: string|null}  $year
     * @param  array{id: int, grade_name: string|null}|null  $enrollment
     */
    private function mapDetail(StudentRecord $record, array $year, ?array $enrollment = null): StudentDetailDTO
    {
        return new StudentDetailDTO(
            id: (int) $record->getKey(),
            publicId: $record->public_id,
            studentCode: (string) $record->student_code,
            nationalId: $record->national_id,
            firstName: (string) $record->first_name,
            middleName: $record->middle_name,
            fatherName: $record->father_name,
            grandfatherName: $record->grandfather_name,
            greatGrandfatherName: $record->great_grandfather_name,
            lastName: (string) $record->last_name,
            motherName: $record->mother_name,
            maternalFatherName: $record->maternal_father_name,
            maternalGrandfatherName: $record->maternal_grandfather_name,
            fullName: (string) $record->full_name,
            guardianTripleName: $record->guardian_triple_name,
            gender: (int) $record->gender,
            birthDate: $record->birth_date->format('Y-m-d'),
            mawalidDate: $record->mawalid_date?->format('Y-m-d'),
            birthPlace: $record->birth_place,
            nationality: $record->nationality,
            governorate: $record->governorate,
            neighborhood: $record->neighborhood,
            locality: $record->locality,
            houseNumber: $record->house_number,
            registrationPlace: $record->registration_place,
            religion: (int) ($record->religion ?? StudentReligion::Muslim->value),
            previousSchoolName: $record->previous_school_name,
            transferDocumentNumber: $record->transfer_document_number !== null
                ? (int) $record->transfer_document_number
                : null,
            transferDocumentDate: $record->transfer_document_date?->format('Y-m-d'),
            schoolStartDate: $record->school_start_date?->format('Y-m-d'),
            admittedClassName: $record->admitted_class_name,
            notes: $record->notes,
            mobile: $record->mobile,
            guardianMobile: $record->guardian_mobile,
            email: $record->email,
            schoolName: $record->school_name,
            branchId: $record->branch_id !== null ? (int) $record->branch_id : null,
            branchName: isset($record->branch_name) && is_string($record->branch_name) && $record->branch_name !== ''
                ? $record->branch_name
                : null,
            departmentName: $record->department_name,

            fatherOccupation: $record->father_occupation,
            motherOccupation: $record->mother_occupation,
            administrativeUnit: $record->administrative_unit !== null ? (int) $record->administrative_unit : null,
            graduationYear: $record->graduation_year !== null ? (int) $record->graduation_year : null,
            previousGpa: $record->previous_gpa !== null ? (float) $record->previous_gpa : null,
            previousStudyTrack: $record->previous_study_track !== null ? (int) $record->previous_study_track : null,
            mathematicsGrade: $record->mathematics_grade !== null ? (float) $record->mathematics_grade : null,
            physicsGrade: $record->physics_grade !== null ? (float) $record->physics_grade : null,
            requestKind: $record->request_kind !== null ? (int) $record->request_kind : null,
            academicYearId: $year['id'],
            academicYearName: $year['name'],
            academicYearCode: $year['code'],
            status: (int) $record->status,
            createdAt: $record->created_at->toIso8601String(),
            updatedAt: $record->updated_at->toIso8601String(),
            activeEnrollmentId: $enrollment['id'] ?? null,
            enrollmentGradeName: $enrollment['grade_name'] ?? null,
        );
    }

    private function applyAcademicYearScope(Builder $query, int $schoolId, ?int $academicYearId): void
    {
        if ($academicYearId === null) {
            return;
        }

        // List queries alias students as `s` (branch join); detail/count keep model table.
        $studentsTable = $this->listStudentsAliasOrTable($query);
        $enrollmentsTable = SchemaHelper::qualified('enrollment', 'enrollments');

        $applicationsTable = SchemaHelper::qualified('admission', 'applications');
        $periodsTable = SchemaHelper::qualified('admission', 'application_periods');

        $query->where(function (Builder $builder) use (
            $studentsTable,
            $enrollmentsTable,
            $applicationsTable,
            $periodsTable,
            $schoolId,
            $academicYearId,
        ): void {
            $builder
                ->where($studentsTable.'.admitted_academic_year_id', $academicYearId)
                ->orWhereExists(function ($exists) use ($studentsTable, $enrollmentsTable, $schoolId, $academicYearId): void {
                    $exists->selectRaw('1')
                        ->from($enrollmentsTable)
                        ->whereColumn($enrollmentsTable.'.student_id', $studentsTable.'.id')
                        ->where($enrollmentsTable.'.school_id', $schoolId)
                        ->where($enrollmentsTable.'.academic_year_id', $academicYearId);
                })
                ->orWhereExists(function ($exists) use (
                    $studentsTable,
                    $applicationsTable,
                    $periodsTable,
                    $schoolId,
                    $academicYearId,
                ): void {
                    $exists->selectRaw('1')
                        ->from($applicationsTable)
                        ->join($periodsTable, $periodsTable.'.id', '=', $applicationsTable.'.application_period_id')
                        ->whereColumn($applicationsTable.'.student_id', $studentsTable.'.id')
                        ->where($periodsTable.'.school_id', $schoolId)
                        ->where($periodsTable.'.academic_year_id', $academicYearId);
                });
        });
    }

    private function applyGenderScope(Builder $query, ?int $gender): void
    {
        if ($gender !== 1 && $gender !== 2) {
            return;
        }

        $query->where($this->listStudentsAliasOrTable($query).'.gender', $gender);
    }

    private function applyRequestKindScope(Builder $query, ?int $requestKind): void
    {
        if ($requestKind !== 1 && $requestKind !== 2) {
            return;
        }

        $query->where($this->listStudentsAliasOrTable($query).'.request_kind', $requestKind);
    }

    private function applyEnrollmentScope(
        Builder $query,
        int $schoolId,
        ?int $academicYearId,
        ?bool $enrolled,
    ): void {
        if ($enrolled === null || $academicYearId === null) {
            return;
        }

        $studentsTable = $this->listStudentsAliasOrTable($query);
        $enrollmentsTable = SchemaHelper::qualified('enrollment', 'enrollments');

        $exists = function ($existsQuery) use ($studentsTable, $enrollmentsTable, $schoolId, $academicYearId): void {
            $existsQuery->selectRaw('1')
                ->from($enrollmentsTable)
                ->whereColumn($enrollmentsTable.'.student_id', $studentsTable.'.id')
                ->where($enrollmentsTable.'.school_id', $schoolId)
                ->where($enrollmentsTable.'.academic_year_id', $academicYearId)
                ->where($enrollmentsTable.'.status', EnrollmentStatus::ACTIVE)
                ->whereNull($enrollmentsTable.'.effective_to');
        };

        if ($enrolled) {
            $query->whereExists($exists);
        } else {
            $query->whereNotExists($exists);
        }
    }

    /**
     * List/search queries use `from students as s`; countByStatus uses the model table.
     */
    private function listStudentsAliasOrTable(Builder $query): string
    {
        $from = $query->getQuery()->from;

        if (is_string($from) && preg_match('/\bas\s+s\b/i', $from) === 1) {
            return 's';
        }

        return $query->getModel()->getTable();
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{id: int, grade_name: string|null}>
     */
    private function activeEnrollmentStudentIds(array $studentIds, int $schoolId, ?int $academicYearId): array
    {
        if ($studentIds === [] || $academicYearId === null) {
            return [];
        }

        $enrollmentsTable = SchemaHelper::qualified('enrollment', 'enrollments');
        $rows = DB::table($enrollmentsTable.' as e')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 'e.class_id')
            ->leftJoin(SchemaHelper::qualified('academic', 'grade_levels').' as g', 'g.id', '=', 'c.grade_level_id')
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->where('e.status', EnrollmentStatus::ACTIVE)
            ->whereNull('e.effective_to')
            ->whereIn('e.student_id', $studentIds)
            ->orderBy('e.id')
            ->get(['e.id', 'e.student_id', 'g.name as grade_name']);

        $set = [];
        foreach ($rows as $row) {
            $set[(int) $row->student_id] = [
                'id' => (int) $row->id,
                'grade_name' => $row->grade_name !== null ? (string) $row->grade_name : null,
            ];
        }

        return $set;
    }

    /**
     * @return array{id: int|null, name: string|null, code: string|null}
     */
    private function resolveAcademicYearContext(int $studentId, int $schoolId, ?int $preferredYearId): array
    {
        $empty = ['id' => null, 'name' => null, 'code' => null];
        $enrollmentsTable = SchemaHelper::qualified('enrollment', 'enrollments');
        $yearsTable = SchemaHelper::qualified('academic', 'academic_years');

        $enrollmentYearQuery = static function () use ($enrollmentsTable, $yearsTable, $studentId, $schoolId) {
            return DB::table($enrollmentsTable)
                ->join($yearsTable, $yearsTable.'.id', '=', $enrollmentsTable.'.academic_year_id')
                ->where($enrollmentsTable.'.student_id', $studentId)
                ->where($enrollmentsTable.'.school_id', $schoolId)
                ->select([
                    $yearsTable.'.id as id',
                    $yearsTable.'.name as name',
                    $yearsTable.'.code as code',
                ]);
        };

        if ($preferredYearId !== null) {
            $match = $enrollmentYearQuery()
                ->where($enrollmentsTable.'.academic_year_id', $preferredYearId)
                ->first();
            if ($match !== null) {
                return [
                    'id' => (int) $match->id,
                    'name' => (string) $match->name,
                    'code' => (string) $match->code,
                ];
            }

            $admittedPreferred = $this->admittedAcademicYearRow($studentId, $schoolId);
            if ($admittedPreferred !== null && (int) $admittedPreferred->id === $preferredYearId) {
                return [
                    'id' => (int) $admittedPreferred->id,
                    'name' => (string) $admittedPreferred->name,
                    'code' => (string) $admittedPreferred->code,
                ];
            }
        }

        $latest = $enrollmentYearQuery()->orderByDesc($enrollmentsTable.'.id')->first();
        if ($latest !== null) {
            return [
                'id' => (int) $latest->id,
                'name' => (string) $latest->name,
                'code' => (string) $latest->code,
            ];
        }

        $admitted = $this->admittedAcademicYearRow($studentId, $schoolId);
        if ($admitted === null) {
            return $empty;
        }

        return [
            'id' => (int) $admitted->id,
            'name' => (string) $admitted->name,
            'code' => (string) $admitted->code,
        ];
    }

    private function admittedAcademicYearRow(int $studentId, int $schoolId): ?object
    {
        $studentsTable = SchemaHelper::qualified('students', 'students');
        $yearsTable = SchemaHelper::qualified('academic', 'academic_years');

        return DB::table($studentsTable)
            ->join($yearsTable, $yearsTable.'.id', '=', $studentsTable.'.admitted_academic_year_id')
            ->where($studentsTable.'.id', $studentId)
            ->where($studentsTable.'.school_id', $schoolId)
            ->select([
                $yearsTable.'.id as id',
                $yearsTable.'.name as name',
                $yearsTable.'.code as code',
            ])
            ->first();
    }
}
