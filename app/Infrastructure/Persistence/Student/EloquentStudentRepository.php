<?php

namespace App\Infrastructure\Persistence\Student;

use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Data\UpdateStudentData;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;

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

    public function findById(int $studentId): ?Student
    {
        $record = StudentRecord::query()->find($studentId);

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
            'department_name' => $data->departmentName,
            'stage_name' => $data->stageName,
            'section_name' => $data->sectionName,
            'status' => $data->status,
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
            'department_name' => $data->departmentName,
            'stage_name' => $data->stageName,
            'section_name' => $data->sectionName,
        ];
    }
}
