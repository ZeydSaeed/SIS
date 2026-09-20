<?php

namespace App\Infrastructure\Persistence\Student;

use App\Application\Student\Contracts\StudentReadRepositoryInterface;
use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;
use App\Database\SchemaHelper;
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
        'admitted_class_name',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'department_name',
        'specialization_name',
        'stage_name',
        'section_name',
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
        'admitted_class_name',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'department_name',
        'specialization_name',
        'stage_name',
        'section_name',
        'status',
        'created_at',
        'updated_at',
    ];

    public function findDetail(int $studentId, int $schoolId, ?int $academicYearId = null): ?StudentDetailDTO
    {
        $record = StudentRecord::query()
            ->select(self::DETAIL_COLUMNS)
            ->whereKey($studentId)
            ->where('school_id', $schoolId)
            ->first();

        if ($record === null) {
            return null;
        }

        return $this->mapDetail(
            $record,
            $this->resolveAcademicYearContext($studentId, $schoolId, $academicYearId),
        );
    }

    public function paginate(
        ?int $status,
        int $schoolId,
        int $page,
        int $perPage,
        ?int $academicYearId = null,
        ?int $gender = null,
    ): array {
        $query = StudentRecord::query()
            ->select(self::LIST_COLUMNS)
            ->where('school_id', $schoolId)
            ->orderByDesc('id');

        $this->applyAcademicYearScope($query, $schoolId, $academicYearId);
        $this->applyGenderScope($query, $gender);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginateQuery($query, $page, $perPage);
    }

    public function search(
        string $term,
        int $schoolId,
        int $page,
        int $perPage,
        ?int $status = null,
        ?int $academicYearId = null,
        ?int $gender = null,
    ): array {
        $term = trim($term);
        $query = StudentRecord::query()
            ->select(self::LIST_COLUMNS)
            ->where('school_id', $schoolId)
            ->orderBy('full_name');

        $this->applyAcademicYearScope($query, $schoolId, $academicYearId);
        $this->applyGenderScope($query, $gender);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($term !== '') {
            $likeOperator = SchemaHelper::isPostgreSql() ? 'ilike' : 'like';
            $pattern = '%'.$term.'%';

            $query->where(function (Builder $builder) use ($likeOperator, $pattern): void {
                $builder->where('full_name', $likeOperator, $pattern)
                    ->orWhere('student_code', $likeOperator, $pattern)
                    ->orWhere('national_id', $likeOperator, $pattern)
                    ->orWhere('first_name', $likeOperator, $pattern)
                    ->orWhere('father_name', $likeOperator, $pattern)
                    ->orWhere('grandfather_name', $likeOperator, $pattern)
                    ->orWhere('great_grandfather_name', $likeOperator, $pattern)
                    ->orWhere('last_name', $likeOperator, $pattern)
                    ->orWhere('mother_name', $likeOperator, $pattern)
                    ->orWhere('maternal_father_name', $likeOperator, $pattern)
                    ->orWhere('maternal_grandfather_name', $likeOperator, $pattern);
            });
        }

        return $this->paginateQuery($query, $page, $perPage);
    }

    /**
     * @return array<int, int>
     */
    public function countByStatus(int $schoolId, ?int $academicYearId = null, ?int $gender = null): array
    {
        $query = StudentRecord::query()->where('school_id', $schoolId);
        $this->applyAcademicYearScope($query, $schoolId, $academicYearId);
        $this->applyGenderScope($query, $gender);

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
    private function paginateQuery(Builder $query, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $paginator = $query->paginate($perPage, self::LIST_COLUMNS, 'page', $page);

        /** @var list<StudentListItemDTO> $items */
        $items = collect($paginator->items())
            ->map(fn (StudentRecord $record): StudentListItemDTO => $this->mapListItem($record))
            ->all();

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

    private function mapListItem(StudentRecord $record): StudentListItemDTO
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
            departmentName: $record->department_name,
            specializationName: $record->specialization_name,
            stageName: $record->stage_name,
            sectionName: $record->section_name,
            status: (int) $record->status,
        );
    }

    /**
     * @param  array{id: int|null, name: string|null, code: string|null}  $year
     */
    private function mapDetail(StudentRecord $record, array $year): StudentDetailDTO
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
            departmentName: $record->department_name,
            specializationName: $record->specialization_name,
            stageName: $record->stage_name,
            sectionName: $record->section_name,
            academicYearId: $year['id'],
            academicYearName: $year['name'],
            academicYearCode: $year['code'],
            status: (int) $record->status,
            createdAt: $record->created_at->toIso8601String(),
            updatedAt: $record->updated_at->toIso8601String(),
        );
    }

    private function applyAcademicYearScope(Builder $query, int $schoolId, ?int $academicYearId): void
    {
        if ($academicYearId === null) {
            return;
        }

        $studentsTable = $query->getModel()->getTable();
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

        $query->where('gender', $gender);
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
