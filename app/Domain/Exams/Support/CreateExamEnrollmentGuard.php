<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Exceptions\AcademicEnrollmentInactiveException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * CreateExamEnrollment preconditions — year match, active seat eligibility, session not Cancelled.
 */
final class CreateExamEnrollmentGuard
{
    public static function assertReady(
        ExamSessionSnapshot $session,
        ExamSnapshot $exam,
        int $enrollmentId,
        int $enrollmentSchoolId,
        int $enrollmentAcademicYearId,
        bool $enrollmentActive,
        bool $seatAlreadyExists,
    ): void {
        if ($enrollmentSchoolId !== $session->schoolId || $exam->schoolId !== $session->schoolId) {
            throw ExamValidationException::sessionNotFound();
        }

        if ($session->status === ExamSessionStatus::Cancelled->value) {
            throw ExamValidationException::sessionCancelledForEnrollment();
        }

        if (! $enrollmentActive) {
            throw AcademicEnrollmentInactiveException::forId($enrollmentId);
        }

        if ($enrollmentAcademicYearId !== $exam->academicYearId) {
            throw ExamValidationException::academicYearMismatchForEnrollment();
        }

        if ($seatAlreadyExists) {
            throw ExamValidationException::duplicateExamEnrollment();
        }
    }
}
