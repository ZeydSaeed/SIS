<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Contracts\PrerequisiteCatalogPort;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;

/**
 * Assign enrollment-subject preconditions — keeps Application handler within ARCH-103.
 */
final class AssignEnrollmentSubjectGuard
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
        private readonly PrerequisiteCatalogPort $catalog,
    ) {}

    public function rejectionCode(
        int $schoolId,
        int $enrollmentId,
        int $subjectId,
    ): ?string {
        $enrollment = $this->enrollments->findByIdAndSchool($enrollmentId, $schoolId);
        if ($enrollment === null || ! $enrollment->isActive()) {
            return 'enrollment.not_found';
        }
        if (! $this->catalog->subjectIsActive($subjectId)) {
            return 'curriculum.subject_not_found';
        }

        foreach ($this->catalog->activePrerequisiteSubjectIds($subjectId) as $prereqSubjectId) {
            if (! $this->enrollmentSubjects->studentHasSubjectHistory($schoolId, $enrollment->studentId, $prereqSubjectId)) {
                return 'enrollment.prerequisite_not_met';
            }
        }

        return null;
    }
}
