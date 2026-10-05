<?php

namespace App\Domain\Student\Contracts;

/**
 * The student's current placement lives on its active enrollment (school + academic
 * year, with placement history). The Student context reads it and asks Enrollment
 * to move it — it never writes enrollment rows itself.
 */
interface StudentEnrollmentPlacementPort
{
    /**
     * The student's active enrollment (latest academic year), or null when not enrolled.
     *
     * @return array{
     *     enrollment_id: int,
     *     branch_id: int|null,
     *     department_id: int|null,
     *     grade_level_id: int|null,
     *     grade_level_name: string|null
     * }|null
     */
    public function activePlacement(int $studentId, int $schoolId): ?array;

    /**
     * Move the active enrollment to another branch / department in the same class and
     * section (placement history + the new department's curriculum, as on the enrollments page).
     */
    public function moveToDepartment(int $enrollmentId, int $schoolId, ?int $branchId, ?int $departmentId): void;
}
