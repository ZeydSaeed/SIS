<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamEnrollmentSnapshot;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;

/**
 * U08 — CancelExamEnrollment (DL-7.2-U08-001 / HD-U08-001/002/003 / DR-002).
 */
final class CancelExamEnrollmentGuard
{
    /**
     * @return bool true when already Withdrawn (HD-U08-001 idempotent no-op)
     */
    public static function assertCancellable(
        ExamEnrollmentSnapshot $enrollment,
        bool $hasCurrentGrade,
    ): bool {
        $status = ExamEnrollmentStatus::from($enrollment->status);

        if ($status === ExamEnrollmentStatus::Withdrawn) {
            return true;
        }

        if ($status === ExamEnrollmentStatus::Absent) {
            throw ExamUpdateForbiddenException::cannotCancelAbsentEnrollment();
        }

        if (! $status->isActiveSeat()) {
            throw ExamUpdateForbiddenException::invalidEnrollmentTransition(
                $status->name,
                ExamEnrollmentStatus::Withdrawn->name,
            );
        }

        if ($hasCurrentGrade) {
            throw ExamCancelBlockedException::enrollmentCurrentGradeExists();
        }

        return false;
    }
}
