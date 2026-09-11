<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * CancelExamSession — HD-002A/B + HD-003 (no Restore, no grade mutation).
 */
final class ExamSessionCancelGuard
{
    /**
     * @return bool true when already Cancelled (HD-002A idempotent no-op)
     */
    public static function assertCancellable(ExamSessionSnapshot $session, bool $hasCurrentGrade): bool
    {
        $status = ExamSessionStatus::from($session->status);

        if ($status === ExamSessionStatus::Cancelled) {
            return true;
        }

        if ($status === ExamSessionStatus::Completed) {
            throw ExamCancelBlockedException::sessionCompleted();
        }

        if ($status !== ExamSessionStatus::Scheduled && $status !== ExamSessionStatus::InProgress) {
            throw ExamCancelBlockedException::sessionCompleted();
        }

        if ($hasCurrentGrade) {
            throw ExamCancelBlockedException::sessionCurrentGradeExists();
        }

        return false;
    }
}
