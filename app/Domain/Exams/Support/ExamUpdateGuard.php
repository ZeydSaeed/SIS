<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Exceptions\ExamCompletionBlockedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\ValueObjects\ExamStatus;

/**
 * DR-003 mutability allowlists + Exam status lifecycle transitions (Master Lock §10.1).
 * Cancelled is never reached through UpdateExam — use CancelExam (exam.cancel).
 */
final class ExamUpdateGuard
{
    /**
     * @param  array{
     *   name?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   exam_type_id?: int,
     *   term_id?: int
     * }  $fields
     */
    public static function assertMetadataAllowed(ExamSnapshot $exam, array $fields): void
    {
        $status = ExamStatus::from($exam->status);

        if ($status === ExamStatus::InProgress
            || $status === ExamStatus::Completed
            || $status === ExamStatus::Cancelled) {
            if ($fields !== []) {
                throw ExamUpdateForbiddenException::metadataNotAllowed($status->name);
            }

            return;
        }

        $allowed = ['name', 'start_date', 'end_date'];
        if ($status === ExamStatus::Draft) {
            $allowed = [...$allowed, 'exam_type_id', 'term_id'];
        }

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw ExamUpdateForbiddenException::fieldNotMutable($field, $status->name);
            }
        }
    }

    /**
     * @param  array{total: int, scheduled: int, in_progress: int, completed: int, cancelled: int}  $sessionCounts
     */
    public static function assertStatusTransition(ExamStatus $from, ExamStatus $to, array $sessionCounts): void
    {
        if ($from->isTerminal()) {
            throw ExamUpdateForbiddenException::terminalStatus($from->name);
        }

        if ($to === ExamStatus::Cancelled) {
            throw ExamUpdateForbiddenException::useCancelExam();
        }

        $allowed = match ($from) {
            ExamStatus::Draft => [ExamStatus::Scheduled],
            ExamStatus::Scheduled => [ExamStatus::InProgress],
            ExamStatus::InProgress => [ExamStatus::Completed],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ExamUpdateForbiddenException::invalidTransition($from->name, $to->name);
        }

        if ($to === ExamStatus::Completed) {
            self::assertCompletionPredicates($sessionCounts);
        }
    }

    /**
     * DR-004 — FAIL CLOSED unless all predicates hold.
     *
     * @param  array{total: int, scheduled: int, in_progress: int, completed: int, cancelled: int}  $sessionCounts
     */
    public static function assertCompletionPredicates(array $sessionCounts): void
    {
        if ($sessionCounts['total'] < 1) {
            throw ExamCompletionBlockedException::zeroSessions();
        }

        if ($sessionCounts['scheduled'] > 0) {
            throw ExamCompletionBlockedException::hasScheduledSessions();
        }

        if ($sessionCounts['in_progress'] > 0) {
            throw ExamCompletionBlockedException::hasInProgressSessions();
        }

        $nonCancelled = $sessionCounts['total'] - $sessionCounts['cancelled'];
        if ($sessionCounts['completed'] !== $nonCancelled) {
            throw ExamCompletionBlockedException::incompleteSessions();
        }
    }

    /**
     * @return array{total: int, scheduled: int, in_progress: int, completed: int, cancelled: int}
     */
    public static function emptySessionCounts(): array
    {
        return [
            'total' => 0,
            'scheduled' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
    }
}
