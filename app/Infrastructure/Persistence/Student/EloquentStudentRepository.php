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
        $record = StudentRecord::query()->create([
            'student_code' => $data->studentCode,
            'national_id' => $data->nationalId,
            'first_name' => $data->firstName,
            'middle_name' => $data->middleName,
            'last_name' => $data->lastName,
            'full_name' => $data->fullName,
            'gender' => $data->gender,
            'birth_date' => $data->birthDate,
            'birth_place' => $data->birthPlace,
            'nationality' => $data->nationality,
            'status' => $data->status,
        ]);

        return (int) $record->getKey();
    }

    public function update(int $studentId, UpdateStudentData $data): void
    {
        StudentRecord::query()
            ->whereKey($studentId)
            ->update([
                'national_id' => $data->nationalId,
                'first_name' => $data->firstName,
                'middle_name' => $data->middleName,
                'last_name' => $data->lastName,
                'full_name' => $data->fullName,
                'gender' => $data->gender,
                'birth_date' => $data->birthDate,
                'birth_place' => $data->birthPlace,
                'nationality' => $data->nationality,
            ]);
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
}
