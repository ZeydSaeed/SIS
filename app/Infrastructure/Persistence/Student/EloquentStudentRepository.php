<?php

namespace App\Infrastructure\Persistence\Student;

use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentReligion;
use App\Domain\Student\ValueObjects\StudentStatus;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use DateTimeInterface;

final class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function saveNew(CreateStudentData $data): int
    {
        $record = new StudentRecord;
        $record->forceFill($this->attributesFromCreate($data));
        $record->save();

        return (int) $record->getKey();
    }

    public function update(int $studentId, UpdateStudentData $data): void
    {
        StudentRecord::query()
            ->whereKey($studentId)
            ->update($this->attributesFromUpdate($data));
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
            admittedClassName: $record->admitted_class_name,
            notes: $record->notes,
            mobile: $record->mobile,
            guardianMobile: $record->guardian_mobile,
            email: $record->email,
            schoolName: $record->school_name,
            branchId: $record->branch_id !== null ? (int) $record->branch_id : null,
            departmentName: $record->department_name,
            specializationName: $record->specialization_name,
            stageName: $record->stage_name,
            sectionName: $record->section_name,
        );
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

    public function generateStudentCode(): string
    {
        $next = ((int) StudentRecord::query()->max('id')) + 1;

        return sprintf('STU-%06d', $next);
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
            'admitted_class_name' => $data->admittedClassName,
            'notes' => $data->notes,
            'mobile' => $data->mobile,
            'guardian_mobile' => $data->guardianMobile,
            'email' => $data->email,
            'school_name' => $data->schoolName,
            'branch_id' => $data->branchId,
            'department_name' => $data->departmentName,
            'specialization_name' => $data->specializationName,
            'stage_name' => $data->stageName,
            'section_name' => $data->sectionName,
            'status' => $data->status,
            'admitted_academic_year_id' => $data->admittedAcademicYearId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromUpdate(UpdateStudentData $data): array
    {
        return [
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
            'admitted_class_name' => $data->admittedClassName,
            'notes' => $data->notes,
            'mobile' => $data->mobile,
            'guardian_mobile' => $data->guardianMobile,
            'email' => $data->email,
            'school_name' => $data->schoolName,
            'branch_id' => $data->branchId,
            'department_name' => $data->departmentName,
            'specialization_name' => $data->specializationName,
            'stage_name' => $data->stageName,
            'section_name' => $data->sectionName,
        ];
    }
}
