<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\EnrollmentSubjectSnapshot;

interface EnrollmentSubjectRepositoryInterface
{
    public function studentHasSubjectHistory(int $schoolId, int $studentId, int $subjectId): bool;

    public function assignOrReactivate(
        int $schoolId,
        int $enrollmentId,
        int $subjectId,
        bool $isElective,
        string $createdAt,
    ): int;

    public function findActive(int $schoolId, int $linkId): ?EnrollmentSubjectSnapshot;

    /** @return list<EnrollmentSubjectSnapshot> */
    public function listActive(int $schoolId, int $enrollmentId): array;

    public function deactivate(int $schoolId, int $linkId): bool;
}
