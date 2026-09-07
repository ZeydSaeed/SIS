<?php

namespace App\Application\Enrollment\Contracts;

use App\Application\Enrollment\DTOs\EnrollmentDTO;

interface EnrollmentReadRepositoryInterface
{
    public function findDetail(int $enrollmentId, int $schoolId): ?EnrollmentDTO;

    /**
     * @return array{items: list<EnrollmentDTO>, pagination: array<string, int>}
     */
    public function paginate(int $schoolId, ?int $academicYearId, int $page, int $perPage): array;
}
