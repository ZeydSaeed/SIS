<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Contracts\EnrollmentCurriculumPort;
use App\Domain\Enrollment\Contracts\PrerequisiteCatalogPort;
use App\Domain\Enrollment\Contracts\PrerequisitePassEvidencePort;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;

/**
 * Assign enrollment-subject preconditions — keeps Application handler within ARCH-103.
 *
 * - Required subjects must belong to the curriculum governing the placement;
 *   electives may come from outside it.
 * - Every active prerequisite needs a finalized passing grade (studying the
 *   prerequisite without passing it does not satisfy it).
 */
final class AssignEnrollmentSubjectGuard
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly PrerequisiteCatalogPort $catalog,
        private readonly PrerequisitePassEvidencePort $passEvidence,
        private readonly EnrollmentCurriculumPort $curriculum,
    ) {}

    public function rejectionCode(
        int $schoolId,
        int $enrollmentId,
        int $subjectId,
        bool $isElective = false,
    ): ?string {
        $enrollment = $this->enrollments->findByIdAndSchool($enrollmentId, $schoolId);
        if ($enrollment === null || ! $enrollment->isActive()) {
            return 'enrollment.not_found';
        }

        if (! $this->catalog->subjectIsActive($subjectId)) {
            return 'curriculum.subject_not_found';
        }

        if (! $isElective && ! $this->curriculum->subjectInGoverningCurriculum(
            $schoolId,
            $enrollment->academicYearId,
            $enrollment->classId,
            $enrollment->departmentId,
            $subjectId,
        )) {
            return 'enrollment.subject_not_in_curriculum';
        }

        foreach ($this->catalog->activePrerequisiteSubjectIds($subjectId) as $prereqSubjectId) {
            if (! $this->passEvidence->studentHasPassingGrade(
                $schoolId,
                $enrollment->studentId,
                $prereqSubjectId,
            )) {
                return 'enrollment.prerequisite_not_met';
            }
        }

        return null;
    }
}
