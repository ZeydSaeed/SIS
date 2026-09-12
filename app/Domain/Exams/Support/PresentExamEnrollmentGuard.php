<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamEnrollmentSnapshot;
use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * U09 — PresentExamEnrollment (HD-7.2-005 / HD-U09-001..005 / DL Final Lock).
 */
final class PresentExamEnrollmentGuard
{
    /**
     * @return bool true when already Present under InProgress (HD-U09-004 idempotent no-op)
     */
    public static function assertPresentable(
        ExamEnrollmentSnapshot $enrollment,
        ExamSessionSnapshot $session,
    ): bool {
        if ($session->schoolId !== $enrollment->schoolId) {
            throw ExamValidationException::examEnrollmentNotFound();
        }

        $status = ExamEnrollmentStatus::from($enrollment->status);

        if ($status === ExamEnrollmentStatus::Present) {
            if ($session->status !== ExamSessionStatus::InProgress->value) {
                throw ExamValidationException::sessionNotInProgressForPresent();
            }

            return true;
        }

        if ($status !== ExamEnrollmentStatus::Confirmed) {
            throw ExamUpdateForbiddenException::invalidEnrollmentTransition(
                $status->name,
                ExamEnrollmentStatus::Present->name,
            );
        }

        if ($session->status !== ExamSessionStatus::InProgress->value) {
            throw ExamValidationException::sessionNotInProgressForPresent();
        }

        return false;
    }
}
