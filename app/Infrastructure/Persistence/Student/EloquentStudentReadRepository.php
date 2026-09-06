<?php

namespace App\Infrastructure\Persistence\Student;

use App\Domain\Enrollment\Data\StudentEnrollmentView;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;

final class EloquentStudentReadRepository implements StudentReadRepositoryInterface
{
    public function findForEnrollment(int $studentId): ?StudentEnrollmentView
    {
        $record = StudentRecord::query()->find($studentId);

        if ($record === null) {
            return null;
        }

        return new StudentEnrollmentView(
            id: (int) $record->getKey(),
            status: (int) $record->status,
            studentCode: (string) $record->student_code,
            fullName: (string) $record->full_name,
        );
    }
}
