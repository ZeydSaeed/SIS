<?php

namespace App\Infrastructure\Persistence\Student;

use App\Database\SchemaHelper;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\Exceptions\InvalidStudentPlacementException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentReligion;
use App\Domain\Student\ValueObjects\StudentStatus;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function __construct(
        private readonly StudentPlacementIdResolver $placementIds = new StudentPlacementIdResolver,
    ) {}

    public function saveNew(CreateStudentData $data): int
    {
        $attributes = $this->attributesFromCreate($data);
        if ($data->schoolId !== null) {
            $attributes = array_merge($attributes, $this->placementAttributes(
                $data->schoolId,
                $data->branchId,
                $data->departmentName,
                $data->admittedClassName,
                null,
            ));
        }

        $record = new StudentRecord;
        $record->forceFill($attributes);
        $record->save();

        return (int) $record->getKey();
    }

    public function update(int $studentId, UpdateStudentData $data): void
    {
        $attributes = $this->attributesFromUpdate($data);
        $current = $this->placementQuery()->where('s.id', $studentId)->first();
        if ($current !== null) {
            $attributes = array_merge($attributes, $this->placementAttributes(
                (int) $current->school_id,
                $data->branchId,
                $data->departmentName,
                $data->admittedClassName,
                $current,
            ));
        }

        StudentRecord::query()
            ->whereKey($studentId)
            ->update($attributes);
    }

    public function findUpdateData(int $studentId, int $schoolId): ?UpdateStudentData
    {
        $record = StudentRecord::query()
            ->whereKey($studentId)
            ->where('school_id', $schoolId)
            ->first();

        if ($record === null) {
            return null;
        }
        $placement = $this->placementQuery()->where('s.id', $studentId)->first();

        return new UpdateStudentData(
            firstName: (string) $record->first_name,
            middleName: $record->middle_name,
            fatherName: $record->father_name,
            grandfatherName: $record->grandfather_name,
            greatGrandfatherName: $record->great_grandfather_name,
            motherName: $record->mother_name,
            maternalFatherName: $record->maternal_father_name,
            maternalGrandfatherName: $record->maternal_grandfather_name,
            lastName: (string) $record->last_name,
            fullName: (string) $record->full_name,
            gender: (int) $record->gender,
            birthDate: $this->dateString($record->birth_date) ?? '',
            nationalId: $record->national_id,
            birthPlace: $record->birth_place,
            nationality: $record->nationality,
            guardianTripleName: $record->guardian_triple_name,
            governorate: $record->governorate,
            neighborhood: $record->neighborhood,
            locality: $record->locality,
            houseNumber: $record->house_number,
            registrationPlace: $record->registration_place,
            religion: (int) ($record->religion ?? StudentReligion::Muslim->value),
            mawalidDate: $this->dateString($record->mawalid_date),
            previousSchoolName: $record->previous_school_name,
            transferDocumentNumber: $record->transfer_document_number !== null
                ? (int) $record->transfer_document_number
                : null,
            transferDocumentDate: $this->dateString($record->transfer_document_date),
            schoolStartDate: $this->dateString($record->school_start_date),
            admittedClassName: $placement?->grade_level_name,
            notes: $record->notes,
            mobile: $record->mobile,
            guardianMobile: $record->guardian_mobile,
            email: $record->email,
            schoolName: $record->school_name,
            branchId: $record->branch_id !== null ? (int) $record->branch_id : null,
            departmentName: $placement?->department_name,
            fatherOccupation: $record->father_occupation,
            motherOccupation: $record->mother_occupation,
            administrativeUnit: $record->administrative_unit !== null ? (int) $record->administrative_unit : null,
            graduationYear: $record->graduation_year !== null ? (int) $record->graduation_year : null,
            previousGpa: $record->previous_gpa !== null ? (float) $record->previous_gpa : null,
            previousStudyTrack: $record->previous_study_track !== null ? (int) $record->previous_study_track : null,
            mathematicsGrade: $record->mathematics_grade !== null ? (float) $record->mathematics_grade : null,
            physicsGrade: $record->physics_grade !== null ? (float) $record->physics_grade : null,
            admittedAcademicYearId: $record->admitted_academic_year_id !== null
                ? (int) $record->admitted_academic_year_id
                : null,
        );
    }

    public function placementIds(int $studentId): array
    {
        $row = StudentRecord::query()->whereKey($studentId)->first(['school_id', 'branch_id', 'department_id', 'grade_level_id']);

        return [
            'school_id' => $row?->school_id !== null ? (int) $row->school_id : null,
            'branch_id' => $row?->branch_id !== null ? (int) $row->branch_id : null,
            'department_id' => $row?->department_id !== null ? (int) $row->department_id : null,
            'grade_level_id' => $row?->grade_level_id !== null ? (int) $row->grade_level_id : null,
        ];
    }

    public function findById(int $studentId): ?Student
    {
        $record = StudentRecord::query()->find($studentId);

        return $this->mapStudent($record);
    }

    public function findByIdForSchool(int $studentId, int $schoolId): ?Student
    {
        $record = StudentRecord::query()
            ->whereKey($studentId)
            ->where('school_id', $schoolId)
            ->first();

        return $this->mapStudent($record);
    }

    public function updateStatus(int $studentId, int $status): void
    {
        StudentRecord::query()
            ->whereKey($studentId)
            ->update(['status' => $status]);
    }

    public function existsByCode(string $code, ?int $exceptStudentId = null): bool
    {
        $query = StudentRecord::query()->where('student_code', $code);

        if ($exceptStudentId !== null) {
            $query->whereKeyNot($exceptStudentId);
        }

        return $query->exists();
    }

    public function existsByNationalId(string $nationalId, ?int $exceptStudentId = null): bool
    {
        $query = StudentRecord::query()->where('national_id', $nationalId);

        if ($exceptStudentId !== null) {
            $query->whereKeyNot($exceptStudentId);
        }

        return $query->exists();
    }

    public function findIdByNationalIdForSchool(string $nationalId, int $schoolId): ?int
    {
        $id = StudentRecord::query()
            ->where('national_id', $nationalId)
            ->where('school_id', $schoolId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function generateStudentCode(): string
    {
        $next = ((int) StudentRecord::query()->max('id')) + 1;

        return sprintf('STU-%06d', $next);
    }

    /**
     * Placement is stored as ids only; names come back through these joins.
     */
    private function placementQuery(): Builder
    {
        return DB::table(SchemaHelper::qualified('students', 'students').' as s')
            ->leftJoin(SchemaHelper::qualified('organization', 'departments').' as dep', 'dep.id', '=', 's.department_id')
            ->leftJoin(SchemaHelper::qualified('academic', 'grade_levels').' as gl', 'gl.id', '=', 's.grade_level_id')
            ->select([
                's.school_id',
                's.branch_id',
                's.department_id',
                's.grade_level_id',
                'dep.name as department_name',
                'gl.name as grade_level_name',
            ]);
    }

    /**
     * Names in (forms / admission) → ids out. An unchanged name keeps its current id
     * (no round-trip through the catalog); a new name must exist in the catalog.
     *
     * @return array{branch_id: int|null, department_id: int|null, grade_level_id: int|null}
     */
    private function placementAttributes(
        int $schoolId,
        ?int $branchId,
        ?string $departmentName,
        ?string $gradeName,
        ?object $current,
    ): array {
        $departmentName = trim((string) $departmentName);
        $gradeName = trim((string) $gradeName);

        $departmentId = null;
        $departmentBranchId = null;
        if ($departmentName !== '') {
            if ($current !== null && $current->department_id !== null && $departmentName === trim((string) $current->department_name)) {
                $departmentId = (int) $current->department_id;
                $departmentBranchId = DB::table(SchemaHelper::qualified('organization', 'departments'))
                    ->where('id', $departmentId)->value('branch_id');
                $departmentBranchId = $departmentBranchId !== null ? (int) $departmentBranchId : null;
            } else {
                $department = $this->placementIds->findDepartment($schoolId, $branchId, $departmentName)
                    ?? throw InvalidStudentPlacementException::unknownDepartment($departmentName);
                $departmentId = $department['id'];
                $departmentBranchId = $department['branch_id'];
            }

            if ($branchId !== null && $departmentBranchId !== null && $branchId !== $departmentBranchId) {
                throw InvalidStudentPlacementException::departmentOutsideBranch($departmentName);
            }
        }

        $gradeLevelId = null;
        if ($gradeName !== '') {
            $gradeLevelId = $current !== null && $current->grade_level_id !== null && $gradeName === trim((string) $current->grade_level_name)
                ? (int) $current->grade_level_id
                : ($this->placementIds->findGradeLevelId($schoolId, $gradeName)
                    ?? throw InvalidStudentPlacementException::unknownGradeLevel($gradeName));
        }

        return [
            'branch_id' => $branchId ?? $departmentBranchId,
            'department_id' => $departmentId,
            'grade_level_id' => $gradeLevelId,
        ];
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function mapStudent(?StudentRecord $record): ?Student
    {
        if ($record === null) {
            return null;
        }

        return Student::reconstitute(
            id: (int) $record->getKey(),
            code: new StudentCode((string) $record->student_code),
            fullName: (string) $record->full_name,
            status: StudentStatus::from((int) $record->status),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromCreate(CreateStudentData $data): array
    {
        return [
            'school_id' => $data->schoolId,
            'student_code' => $data->studentCode,
            'national_id' => $data->nationalId,
            'first_name' => $data->firstName,
            'middle_name' => $data->middleName,
            'father_name' => $data->fatherName,
            'grandfather_name' => $data->grandfatherName,
            'great_grandfather_name' => $data->greatGrandfatherName,
            'last_name' => $data->lastName,
            'mother_name' => $data->motherName,
            'maternal_father_name' => $data->maternalFatherName,
            'maternal_grandfather_name' => $data->maternalGrandfatherName,
            'full_name' => $data->fullName,
            'guardian_triple_name' => $data->guardianTripleName,
            'gender' => $data->gender,
            'birth_date' => $data->birthDate,
            'mawalid_date' => $data->mawalidDate,
            'birth_place' => $data->birthPlace,
            'nationality' => $data->nationality,
            'governorate' => $data->governorate,
            'neighborhood' => $data->neighborhood,
            'locality' => $data->locality,
            'house_number' => $data->houseNumber,
            'registration_place' => $data->registrationPlace,
            'religion' => $data->religion,
            'previous_school_name' => $data->previousSchoolName,
            'transfer_document_number' => $data->transferDocumentNumber,
            'transfer_document_date' => $data->transferDocumentDate,
            'school_start_date' => $data->schoolStartDate,
            'notes' => $data->notes,
            'mobile' => $data->mobile,
            'guardian_mobile' => $data->guardianMobile,
            'email' => $data->email,
            'school_name' => $data->schoolName,
            'branch_id' => $data->branchId,
            'father_occupation' => $data->fatherOccupation,
            'mother_occupation' => $data->motherOccupation,
            'administrative_unit' => $data->administrativeUnit,
            'graduation_year' => $data->graduationYear,
            'previous_gpa' => $data->previousGpa,
            'previous_study_track' => $data->previousStudyTrack,
            'mathematics_grade' => $data->mathematicsGrade,
            'physics_grade' => $data->physicsGrade,
            'request_kind' => $data->requestKind,
            'status' => $data->status,
            'admitted_academic_year_id' => $data->admittedAcademicYearId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromUpdate(UpdateStudentData $data): array
    {
        $attributes = [
            'national_id' => $data->nationalId,
            'first_name' => $data->firstName,
            'middle_name' => $data->middleName,
            'father_name' => $data->fatherName,
            'grandfather_name' => $data->grandfatherName,
            'great_grandfather_name' => $data->greatGrandfatherName,
            'last_name' => $data->lastName,
            'mother_name' => $data->motherName,
            'maternal_father_name' => $data->maternalFatherName,
            'maternal_grandfather_name' => $data->maternalGrandfatherName,
            'full_name' => $data->fullName,
            'guardian_triple_name' => $data->guardianTripleName,
            'gender' => $data->gender,
            'birth_date' => $data->birthDate,
            'mawalid_date' => $data->mawalidDate,
            'birth_place' => $data->birthPlace,
            'nationality' => $data->nationality,
            'governorate' => $data->governorate,
            'neighborhood' => $data->neighborhood,
            'locality' => $data->locality,
            'house_number' => $data->houseNumber,
            'registration_place' => $data->registrationPlace,
            'religion' => $data->religion,
            'previous_school_name' => $data->previousSchoolName,
            'transfer_document_number' => $data->transferDocumentNumber,
            'transfer_document_date' => $data->transferDocumentDate,
            'school_start_date' => $data->schoolStartDate,
            'notes' => $data->notes,
            'mobile' => $data->mobile,
            'guardian_mobile' => $data->guardianMobile,
            'email' => $data->email,
            'school_name' => $data->schoolName,
            'branch_id' => $data->branchId,
            'father_occupation' => $data->fatherOccupation,
            'mother_occupation' => $data->motherOccupation,
            'administrative_unit' => $data->administrativeUnit,
            'graduation_year' => $data->graduationYear,
            'previous_gpa' => $data->previousGpa,
            'previous_study_track' => $data->previousStudyTrack,
            'mathematics_grade' => $data->mathematicsGrade,
            'physics_grade' => $data->physicsGrade,
        ];

        if ($data->admittedAcademicYearId !== null) {
            $attributes['admitted_academic_year_id'] = $data->admittedAcademicYearId;
        }

        return $attributes;
    }
}
