<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\ValueObjects\ExamStatus;

/** DR-001 — CancelExam fail-closed guards (no grade mutation). */
final class ExamCancelGuard
{
    public static function assertCancellable(
        ExamSnapshot $exam,
        bool $hasCurrentGrade,
        bool $hasCompletedSession,
    ): void {
        $status = ExamStatus::from($exam->status);

        if ($status === ExamStatus::Completed) {
            throw ExamCancelBlockedException::examCompleted();
        }

        if ($status === ExamStatus::Cancelled) {
            throw ExamCancelBlockedException::examAlreadyCancelled();
        }

        if ($hasCurrentGrade) {
            throw ExamCancelBlockedException::currentGradeExists();
        }

        if ($hasCompletedSession) {
            throw ExamCancelBlockedException::completedSessionExists();
        }
    }
}
