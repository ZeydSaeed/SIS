<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * DR-003 — UpdateExamSession allowlist (Scheduled only; status never via Update).
 */
final class ExamSessionUpdateGuard
{
    /**
     * @param  array{
     *   session_date?: string,
     *   start_time?: string,
     *   end_time?: string,
     *   room_id?: int|null,
     *   max_grade?: int,
     *   pass_grade?: int
     * }  $fields
     */
    public static function assertMetadataAllowed(
        ExamSessionSnapshot $session,
        array $fields,
        bool $hasCurrentGrade,
    ): void {
        if ($fields === []) {
            throw ExamValidationException::emptySessionUpdate();
        }

        $status = ExamSessionStatus::from($session->status);
        if ($status !== ExamSessionStatus::Scheduled) {
            throw ExamUpdateForbiddenException::sessionMetadataNotAllowed($status->name);
        }

        $allowed = ['session_date', 'start_time', 'end_time', 'room_id', 'max_grade', 'pass_grade'];
        foreach (array_keys($fields) as $field) {
            if ($field === 'status' || $field === 'exam_id' || $field === 'subject_id' || $field === 'school_id') {
                throw ExamUpdateForbiddenException::sessionStatusOrIdentityViaUpdate($field);
            }

            if (! in_array($field, $allowed, true)) {
                throw ExamUpdateForbiddenException::sessionFieldNotMutable($field, $status->name);
            }
        }

        if ($hasCurrentGrade && (isset($fields['max_grade']) || isset($fields['pass_grade']))) {
            throw ExamUpdateForbiddenException::maxPassFrozenByCurrentGrade();
        }
    }

    /**
     * @param  array{
     *   session_date?: string,
     *   start_time?: string,
     *   end_time?: string,
     *   room_id?: int|null,
     *   max_grade?: int,
     *   pass_grade?: int
     * }  $fields
     */
    public static function assertResolvedValues(ExamSessionSnapshot $session, array $fields): void
    {
        $startTime = $fields['start_time'] ?? $session->startTime;
        $endTime = $fields['end_time'] ?? $session->endTime;
        if ($endTime <= $startTime) {
            throw ExamValidationException::invalidSessionTimeRange();
        }

        $maxGrade = $fields['max_grade'] ?? $session->maxGrade;
        $passGrade = $fields['pass_grade'] ?? $session->passGrade;
        if ($passGrade < 0 || $passGrade > $maxGrade || $maxGrade <= 0) {
            throw ExamValidationException::invalidPassMaxGrade();
        }
    }
}
