<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\CreateEnrollmentData;

interface EnrollmentRepositoryInterface
{
    public function hasActiveEnrollment(int $studentId, int $academicYearId): bool;

    public function save(CreateEnrollmentData $data): int;

    public function generateEnrollmentNumber(int $schoolId, int $academicYearId): string;
}
