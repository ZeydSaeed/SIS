<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Attendance\Exceptions\InvalidEnrollmentForAttendanceException;
use App\Domain\Attendance\Specifications\EnrollmentValidForAttendanceSpecification;

/** ATT-D4 enrollment validity at session_date. */
final class AttendanceEnrollmentGuard
{
    public static function assertValidForMark(
        ?EnrollmentAttendanceContext $enrollment,
        int $enrollmentId,
        int $suppliedStudentId,
        int $resolvedSchoolId,
        int $sessionAcademicYearId,
        int $sessionSectionId,
        string $sessionDate,
    ): EnrollmentAttendanceContext {
        if ($enrollment === null) {
            throw InvalidEnrollmentForAttendanceException::withReasons([
                "Enrollment {$enrollmentId} was not found.",
            ]);
        }

        $spec = new EnrollmentValidForAttendanceSpecification(
            suppliedStudentId: $suppliedStudentId,
            resolvedSchoolId: $resolvedSchoolId,
            sessionAcademicYearId: $sessionAcademicYearId,
            sessionSectionId: $sessionSectionId,
            sessionDate: $sessionDate,
        );

        $reasons = $spec->unsatisfiedReasons($enrollment);
        if ($reasons !== []) {
            throw InvalidEnrollmentForAttendanceException::withReasons($reasons);
        }

        return $enrollment;
    }
}
