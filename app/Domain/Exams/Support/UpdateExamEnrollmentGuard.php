<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Data\ExamEnrollmentSnapshot;
use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * U07 — narrow allowlisted UpdateExamEnrollment transitions and field freezes.
 */
final class UpdateExamEnrollmentGuard
{
    /**
     * @param  array{status?: int, seat_number?: string|null}  $changes
     */
    public static function assertMeaningfulUpdate(array $changes): void
    {
        if ($changes === []) {
            throw ExamValidationException::emptyEnrollmentUpdate();
        }
    }

    /**
     * @param  array{status?: int, seat_number?: string|null}  $changes
     */
    public static function assertAllowed(
        ExamEnrollmentSnapshot $enrollment,
        ExamSessionSnapshot $session,
        array $changes,
        bool $hasCurrentGrade,
    ): void {
        if ($session->schoolId !== $enrollment->schoolId) {
            throw ExamValidationException::examEnrollmentNotFound();
        }

        if ($session->status === ExamSessionStatus::Cancelled->value) {
            throw ExamValidationException::sessionCancelledForEnrollmentUpdate();
        }

        foreach (array_keys($changes) as $field) {
            if (in_array($field, ['exam_session_id', 'enrollment_id', 'school_id'], true)) {
                throw ExamUpdateForbiddenException::enrollmentIdentityImmutable($field);
            }
        }

        if (array_key_exists('seat_number', $changes)) {
            self::assertSeatNumberMutable($enrollment, $hasCurrentGrade);
        }

        if (array_key_exists('status', $changes)) {
            self::assertStatusTransition(
                ExamEnrollmentStatus::from($enrollment->status),
                ExamEnrollmentStatus::from((int) $changes['status']),
            );
        }
    }

    public static function assertSeatNumberMutable(
        ExamEnrollmentSnapshot $enrollment,
        bool $hasCurrentGrade,
    ): void {
        if ($enrollment->status === ExamEnrollmentStatus::Present->value) {
            throw ExamUpdateForbiddenException::seatNumberFrozenByPresent();
        }

        if ($hasCurrentGrade) {
            throw ExamUpdateForbiddenException::seatNumberFrozenByCurrentGrade();
        }
    }

    public static function assertStatusTransition(
        ExamEnrollmentStatus $from,
        ExamEnrollmentStatus $to,
    ): void {
        if ($from === $to) {
            throw ExamValidationException::emptyEnrollmentUpdate();
        }

        $allowed = match ($from) {
            ExamEnrollmentStatus::Registered => [
                ExamEnrollmentStatus::Confirmed,
                ExamEnrollmentStatus::Absent,
            ],
            ExamEnrollmentStatus::Confirmed => [
                ExamEnrollmentStatus::Absent,
            ],
            ExamEnrollmentStatus::Present => [
                ExamEnrollmentStatus::Absent,
            ],
            ExamEnrollmentStatus::Absent, ExamEnrollmentStatus::Withdrawn => [],
        };

        if (! in_array($to, $allowed, true)) {
            if ($from === ExamEnrollmentStatus::Confirmed && $to === ExamEnrollmentStatus::Present) {
                throw ExamUpdateForbiddenException::usePresentExamEnrollment();
            }

            if ($to === ExamEnrollmentStatus::Withdrawn) {
                throw ExamUpdateForbiddenException::useCancelExamEnrollment();
            }

            throw ExamUpdateForbiddenException::invalidEnrollmentTransition($from->name, $to->name);
        }
    }
}
