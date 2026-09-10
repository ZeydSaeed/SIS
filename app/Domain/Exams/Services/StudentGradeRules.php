<?php

namespace App\Domain\Exams\Services;

use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\Exceptions\InvalidGradeScoreException;
use App\Domain\Exams\ValueObjects\GradeStatus;

final class StudentGradeRules
{
    public static function assertScoreSemantics(bool $isAbsent, ?string $score, string $maxScore): void
    {
        if (! is_numeric($maxScore) || (float) $maxScore <= 0) {
            throw InvalidGradeScoreException::forReason('max_score must be greater than zero.');
        }

        if ($isAbsent) {
            if ($score !== null) {
                throw InvalidGradeScoreException::forReason('Absent grades must have a null score.');
            }

            return;
        }

        if ($score === null || ! is_numeric($score)) {
            throw InvalidGradeScoreException::forReason('Non-absent grades require a numeric score.');
        }

        $scoreValue = (float) $score;
        $maxValue = (float) $maxScore;

        if ($scoreValue < 0 || $scoreValue > $maxValue) {
            throw InvalidGradeScoreException::forReason('Score must be between 0 and max_score.');
        }
    }

    public static function assertCanFinalize(GradeStatus $status): void
    {
        if (! in_array($status, [GradeStatus::Draft, GradeStatus::Entered, GradeStatus::Submitted], true)) {
            throw InvalidGradeCorrectionException::forReason(
                'Only draft, entered, or submitted current grades can be finalized.',
            );
        }
    }

    public static function assertCanVoid(bool $isCurrent, GradeStatus $status): void
    {
        if (! $isCurrent) {
            throw InvalidGradeCorrectionException::forReason('Only the current grade can be voided.');
        }

        if ($status === GradeStatus::Voided) {
            throw InvalidGradeCorrectionException::forReason('Grade is already voided.');
        }
    }

    /**
     * @param  list<int>  $chainIds  oldest → newest or walk from current backward
     */
    public static function assertNoCycle(array $chainIds, int $newTargetId): void
    {
        if (in_array($newTargetId, $chainIds, true)) {
            throw InvalidGradeCorrectionException::forReason('Correction would create a cycle.');
        }
    }
}
