<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\StudentEnrollmentView;

interface StudentReadRepositoryInterface
{
    public function findForEnrollment(int $studentId): ?StudentEnrollmentView;
}
