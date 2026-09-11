<?php

namespace App\Domain\Attendance\Specifications;

use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Shared\AbstractSpecification;

/**
 * ATT-D4 — enrollment valid for attendance at session_date (pure PHP).
 */
final class EnrollmentValidForAttendanceSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly int $suppliedStudentId,
        private readonly int $resolvedSchoolId,
        private readonly int $sessionAcademicYearId,
        private readonly int $sessionSectionId,
        private readonly string $sessionDate,
    ) {}

    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->unsatisfiedReasons($candidate) === [];
    }

    /**
     * @return list<string>
     */
    public function unsatisfiedReasons(object $candidate): array
    {
        if (! $candidate instanceof EnrollmentAttendanceContext) {
            return ['Invalid enrollment context for attendance'];
        }

        $reasons = [];

        if ($candidate->studentId !== $this->suppliedStudentId) {
            $reasons[] = 'Enrollment student does not match supplied student';
        }

        if ($candidate->schoolId !== $this->resolvedSchoolId) {
            $reasons[] = 'Enrollment school does not match resolved session school';
        }

        if ($candidate->academicYearId !== $this->sessionAcademicYearId) {
            $reasons[] = 'Enrollment academic year does not match session';
        }

        if ($candidate->sectionId !== $this->sessionSectionId) {
            $reasons[] = 'Enrollment section does not match session';
        }

        if ($candidate->effectiveFrom > $this->sessionDate) {
            $reasons[] = 'Enrollment effective_from is after session date';
        }

        if ($candidate->effectiveTo !== null && $this->sessionDate > $candidate->effectiveTo) {
            $reasons[] = 'Session date is after enrollment effective_to';
        }

        return $reasons;
    }
}
