<?php

namespace App\Domain\Student\Repositories;

use App\Domain\Student\Data\StudentGuardianLinkSnapshot;

interface StudentGuardianRepositoryInterface
{
    /**
     * @return list<StudentGuardianLinkSnapshot>|null null when student not in school
     */
    public function listForStudent(int $schoolId, int $studentId): ?array;
}
