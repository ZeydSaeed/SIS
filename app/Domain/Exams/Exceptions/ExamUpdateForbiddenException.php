<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamUpdateForbiddenException extends DomainException
{
    public static function metadataNotAllowed(string $status): self
    {
        return new self("Metadata updates are not allowed when exam status is {$status}.");
    }

    public static function fieldNotMutable(string $field, string $status): self
    {
        return new self("Field '{$field}' is not mutable when exam status is {$status}.");
    }

    public static function terminalStatus(string $status): self
    {
        return new self("Exam status {$status} is terminal and cannot be changed.");
    }

    public static function useCancelExam(): self
    {
        return new self('Cancelling an exam requires CancelExam with exam.cancel permission.');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Invalid exam status transition from {$from} to {$to}.");
    }

    public static function immutableField(string $field): self
    {
        return new self("Field '{$field}' is immutable.");
    }

    public static function sessionMetadataNotAllowed(string $status): self
    {
        return new self("Exam session metadata updates are not allowed when status is {$status}.");
    }

    public static function sessionFieldNotMutable(string $field, string $status): self
    {
        return new self("Exam session field '{$field}' is not mutable when status is {$status}.");
    }

    public static function sessionStatusOrIdentityViaUpdate(string $field): self
    {
        return new self("Exam session field '{$field}' cannot be changed through UpdateExamSession.");
    }

    public static function maxPassFrozenByCurrentGrade(): self
    {
        return new self('max_grade and pass_grade are immutable while a CURRENT grade exists for the session.');
    }

    public static function invalidSessionTransition(string $from, string $to): self
    {
        return new self("Invalid exam session status transition from {$from} to {$to}.");
    }

    public static function cannotOpenCancelledSession(): self
    {
        return new self('Cancelled exam sessions cannot be opened.');
    }

    public static function cannotReopenCompletedSession(): self
    {
        return new self('Completed exam sessions cannot be reopened.');
    }

    public static function cannotCloseCancelledSession(): self
    {
        return new self('Cancelled exam sessions cannot be closed.');
    }

    public static function invalidEnrollmentTransition(string $from, string $to): self
    {
        return new self("Invalid exam enrollment status transition from {$from} to {$to}.");
    }

    public static function usePresentExamEnrollment(): self
    {
        return new self('Marking an exam enrollment Present requires PresentExamEnrollment with exam.enrollment.present.');
    }

    public static function useCancelExamEnrollment(): self
    {
        return new self('Withdrawing an exam enrollment requires CancelExamEnrollment with exam.enrollment.cancel.');
    }

    public static function enrollmentIdentityImmutable(string $field): self
    {
        return new self("Exam enrollment field '{$field}' is immutable.");
    }

    public static function seatNumberFrozenByPresent(): self
    {
        return new self('seat_number is immutable while the exam enrollment status is Present.');
    }

    public static function seatNumberFrozenByCurrentGrade(): self
    {
        return new self('seat_number is immutable while a CURRENT grade exists for the exam enrollment.');
    }

    public static function cannotCancelAbsentEnrollment(): self
    {
        return new self('Absent exam enrollments cannot be cancelled to Withdrawn (HD-U08-002).');
    }
}
