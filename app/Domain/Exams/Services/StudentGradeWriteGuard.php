<?php

namespace App\Domain\Exams\Services;

use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Exams\Data\ExamEnrollmentGradeContext;
use App\Domain\Exams\Data\StudentGradeSnapshot;
use App\Domain\Exams\Exceptions\AcademicEnrollmentInactiveException;
use App\Domain\Exams\Exceptions\CurrentGradeAlreadyExistsException;
use App\Domain\Exams\Exceptions\ExamSessionUnavailableException;
use App\Domain\Exams\Exceptions\InactiveExamSeatException;
use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Domain\Exams\ValueObjects\GradeStatus;

/**
 * Domain preconditions for grade writes — keeps Application handlers thin (ARCH-103).
 */
final class StudentGradeWriteGuard
{
    public static function assertCanEnter(
        ExamEnrollmentGradeContext $context,
        ?StudentGradeSnapshot $current,
        bool $isAbsent,
        ?string $score,
    ): void {
        $seat = ExamEnrollmentStatus::tryFrom($context->examEnrollmentStatus);
        if ($seat === null || ! $seat->isActiveSeat()) {
            throw InactiveExamSeatException::forId($context->examEnrollmentId);
        }

        if ($context->sessionStatus === ExamSessionStatus::Cancelled->value
            || $context->examStatus === ExamStatus::Cancelled->value) {
            throw ExamSessionUnavailableException::cancelled($context->examSessionId);
        }

        if (! EnrollmentStatus::isActive($context->academicEnrollmentStatus, $context->academicEnrollmentEffectiveTo)) {
            throw AcademicEnrollmentInactiveException::forId($context->enrollmentId);
        }

        StudentGradeRules::assertScoreSemantics($isAbsent, $score, $context->maxScore);

        if ($current !== null) {
            throw CurrentGradeAlreadyExistsException::forExamEnrollment($context->examEnrollmentId);
        }
    }

    /**
     * @param  list<int>  $correctionChainIds
     */
    public static function assertCanCorrect(
        StudentGradeSnapshot $current,
        bool $isAbsent,
        ?string $score,
        array $correctionChainIds,
    ): void {
        if (! $current->isCurrent) {
            throw InvalidGradeCorrectionException::forReason('Only the current grade can be corrected.');
        }

        StudentGradeRules::assertCanVoid($current->isCurrent, GradeStatus::from($current->status));
        StudentGradeRules::assertScoreSemantics($isAbsent, $score, $current->maxScore);

        if (count($correctionChainIds) !== count(array_unique($correctionChainIds))) {
            throw InvalidGradeCorrectionException::forReason('Correction chain is corrupted (cycle detected).');
        }
    }
}
