<?php

namespace App\Infrastructure\Persistence\Student;

use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;

final class EloquentStudentReadRepository implements StudentReadRepositoryInterface
{
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
}
