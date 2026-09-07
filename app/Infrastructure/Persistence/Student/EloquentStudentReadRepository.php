<?php

namespace App\Infrastructure\Persistence\Student;

use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class EloquentStudentReadRepository implements StudentReadRepositoryInterface
{
    public function __construct(
        private readonly StudentRepositoryInterface $students,
    ) {}

    public function findById(int $studentId): ?Student
    {
        return $this->students->findById($studentId);
    }
}
