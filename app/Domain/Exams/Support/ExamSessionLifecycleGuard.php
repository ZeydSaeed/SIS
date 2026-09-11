<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;

/**
 * OpenExamSession / CloseExamSession transitions — no Restore, no CompleteExam.
 */
final class ExamSessionLifecycleGuard
{
    public static function assertCanOpen(ExamSessionSnapshot $session, ExamSnapshot $exam): void
    {
        if ($exam->status === ExamStatus::Cancelled->value) {
            throw ExamValidationException::parentExamCancelledForOpen();
        }

        $status = ExamSessionStatus::from($session->status);

        if ($status === ExamSessionStatus::Cancelled) {
            throw ExamUpdateForbiddenException::cannotOpenCancelledSession();
        }

        if ($status === ExamSessionStatus::Completed) {
            throw ExamUpdateForbiddenException::cannotReopenCompletedSession();
        }

        if ($status !== ExamSessionStatus::Scheduled) {
            throw ExamUpdateForbiddenException::invalidSessionTransition(
                $status->name,
                ExamSessionStatus::InProgress->name,
            );
        }
    }

    public static function assertCanClose(ExamSessionSnapshot $session, ExamSnapshot $exam): void
    {
        if ($exam->status === ExamStatus::Cancelled->value) {
            throw ExamValidationException::parentExamCancelledForClose();
        }

        $status = ExamSessionStatus::from($session->status);

        if ($status === ExamSessionStatus::Cancelled) {
            throw ExamUpdateForbiddenException::cannotCloseCancelledSession();
        }

        if ($status !== ExamSessionStatus::InProgress) {
            throw ExamUpdateForbiddenException::invalidSessionTransition(
                $status->name,
                ExamSessionStatus::Completed->name,
            );
        }
    }
}
