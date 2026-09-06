<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Student\Entities\Student;

interface StudentReadRepositoryInterface
{
    public function findById(int $studentId): ?Student;
}
