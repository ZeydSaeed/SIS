<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ExamUpdateForbiddenException extends SisDomainException
{
    public static function metadataNotAllowed(string $status): self
    {
        return new self("Metadata updates are not allowed when exam status is {$status}.", 'exams.update.metadata_not_allowed');
    }

    public static function fieldNotMutable(string $field, string $status): self
    {
        return new self("Field '{$field}' is not mutable when exam status is {$status}.", 'exams.update.field_not_mutable');
    }

    public static function terminalStatus(string $status): self
    {
        return new self("Exam status {$status} is terminal and cannot be changed.", 'exams.update.terminal_status');
    }

    public static function useCancelExam(): self
    {
        return new self('Cancelling an exam requires CancelExam with exam.cancel permission.', 'exams.update.use_cancel_exam');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Invalid exam status transition from {$from} to {$to}.", 'exams.update.invalid_transition');
    }

    public static function immutableField(string $field): self
    {
        return new self("Field '{$field}' is immutable.", 'exams.update.immutable_field');
    }

    public static function sessionMetadataNotAllowed(string $status): self
    {
        return new self("Exam session metadata updates are not allowed when status is {$status}.", 'exams.update.session_metadata_not_allowed');
    }

    public static function sessionFieldNotMutable(string $field, string $status): self
    {
        return new self("Exam session field '{$field}' is not mutable when status is {$status}.", 'exams.update.session_field_not_mutable');
    }

    public static function sessionStatusOrIdentityViaUpdate(string $field): self
    {
        return new self("Exam session field '{$field}' cannot be changed through UpdateExamSession.", 'exams.update.session_status_or_identity_via_update');
    }

    public static function maxPassFrozenByCurrentGrade(): self
    {
        return new self('max_grade and pass_grade are immutable while a CURRENT grade exists for the session.', 'exams.update.max_pass_frozen_by_current_grade');
    }

    public static function invalidSessionTransition(string $from, string $to): self
    {
        return new self("Invalid exam session status transition from {$from} to {$to}.", 'exams.update.invalid_session_transition');
    }

    public static function cannotOpenCancelledSession(): self
    {
        return new self('Cancelled exam sessions cannot be opened.', 'exams.update.cannot_open_cancelled_session');
    }

    public static function cannotReopenCompletedSession(): self
    {
        return new self('Completed exam sessions cannot be reopened.', 'exams.update.cannot_reopen_completed_session');
    }

    public static function cannotCloseCancelledSession(): self
    {
        return new self('Cancelled exam sessions cannot be closed.', 'exams.update.cannot_close_cancelled_session');
    }

    public static function invalidEnrollmentTransition(string $from, string $to): self
    {
        return new self("Invalid exam enrollment status transition from {$from} to {$to}.", 'exams.update.invalid_enrollment_transition');
    }

    public static function usePresentExamEnrollment(): self
    {
        return new self('Marking an exam enrollment Present requires PresentExamEnrollment with exam.enrollment.present.', 'exams.update.use_present_exam_enrollment');
    }

    public static function useCancelExamEnrollment(): self
    {
        return new self('Withdrawing an exam enrollment requires CancelExamEnrollment with exam.enrollment.cancel.', 'exams.update.use_cancel_exam_enrollment');
    }

    public static function enrollmentIdentityImmutable(string $field): self
    {
        return new self("Exam enrollment field '{$field}' is immutable.", 'exams.update.enrollment_identity_immutable');
    }

    public static function seatNumberFrozenByPresent(): self
    {
        return new self('seat_number is immutable while the exam enrollment status is Present.', 'exams.update.seat_number_frozen_by_present');
    }

    public static function seatNumberFrozenByCurrentGrade(): self
    {
        return new self('seat_number is immutable while a CURRENT grade exists for the exam enrollment.', 'exams.update.seat_number_frozen_by_current_grade');
    }

    public static function cannotCancelAbsentEnrollment(): self
    {
        return new self('Absent exam enrollments cannot be cancelled to Withdrawn (HD-U08-002).', 'exams.update.cannot_cancel_absent_enrollment');
    }
}
