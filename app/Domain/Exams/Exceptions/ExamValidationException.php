<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ExamValidationException extends SisDomainException
{
    public static function invalidDates(): self
    {
        return new self('Exam end_date must be greater than or equal to start_date.', 'exams.validation.invalid_dates');
    }

    public static function missingAcademicYear(): self
    {
        return new self('Academic year does not exist.', 'exams.validation.missing_academic_year');
    }

    public static function missingTerm(): self
    {
        return new self('Term does not exist for the academic year.', 'exams.validation.missing_term');
    }

    public static function missingExamType(): self
    {
        return new self('Exam type does not exist.', 'exams.validation.missing_exam_type');
    }

    public static function missingIdempotencyKey(): self
    {
        return new self('Idempotency key is required for exam administration commands.', 'exams.validation.missing_idempotency_key');
    }

    public static function emptyUpdate(): self
    {
        return new self('UpdateExam requires at least one allowlisted field or a status transition.', 'exams.validation.empty_update');
    }

    public static function parentExamCancelled(): self
    {
        return new self('Cannot create an exam session under a Cancelled exam.', 'exams.validation.parent_exam_cancelled');
    }

    public static function parentExamNotFound(): self
    {
        return new self('Exam does not exist in the current school.', 'exams.validation.parent_exam_missing');
    }

    public static function missingSubject(): self
    {
        return new self('Subject does not exist.', 'exams.validation.missing_subject');
    }

    public static function invalidSessionTimeRange(): self
    {
        return new self('Session end_time must be greater than start_time.', 'exams.validation.invalid_session_time_range');
    }

    public static function invalidPassMaxGrade(): self
    {
        return new self('pass_grade must be between 0 and max_grade.', 'exams.validation.invalid_pass_max_grade');
    }

    public static function roomNotInSchool(): self
    {
        return new self('Room does not belong to the current school.', 'exams.validation.room_not_in_school');
    }

    public static function missingIdempotencyKeyForSession(): self
    {
        return new self('Idempotency key is required for exam session commands.', 'exams.validation.missing_idempotency_key_for_session');
    }

    public static function emptySessionUpdate(): self
    {
        return new self('UpdateExamSession requires at least one allowlisted field.', 'exams.validation.empty_session_update');
    }

    public static function sessionNotFound(): self
    {
        return new self('Exam session does not exist in the current school.', 'exams.validation.session_missing');
    }

    public static function parentExamCancelledForOpen(): self
    {
        return new self('Cannot open an exam session under a Cancelled exam.', 'exams.validation.parent_exam_cancelled_for_open');
    }

    public static function parentExamCancelledForClose(): self
    {
        return new self('Cannot close an exam session under a Cancelled exam.', 'exams.validation.parent_exam_cancelled_for_close');
    }

    public static function missingIdempotencyKeyForEnrollment(): self
    {
        return new self('Idempotency key is required for exam enrollment commands.', 'exams.validation.missing_idempotency_key_for_enrollment');
    }

    public static function academicEnrollmentNotFound(): self
    {
        return new self('Academic enrollment does not exist in the current school.', 'exams.validation.academic_enrollment_missing');
    }

    public static function academicYearMismatchForEnrollment(): self
    {
        return new self('Academic enrollment academic_year_id must equal the exam academic_year_id.', 'exams.validation.academic_year_mismatch_for_enrollment');
    }

    public static function sessionCancelledForEnrollment(): self
    {
        return new self('Cannot create an exam enrollment under a Cancelled session.', 'exams.validation.session_cancelled_for_enrollment');
    }

    public static function duplicateExamEnrollment(): self
    {
        return new self('An exam enrollment already exists for this session and academic enrollment.', 'exams.validation.duplicate_exam_enrollment');
    }

    public static function emptyEnrollmentUpdate(): self
    {
        return new self('UpdateExamEnrollment requires a meaningful status transition or seat_number change.', 'exams.validation.empty_enrollment_update');
    }

    public static function examEnrollmentNotFound(): self
    {
        return new self('Exam enrollment does not exist in the current school.', 'exams.validation.exam_enrollment_missing');
    }

    public static function sessionCancelledForEnrollmentUpdate(): self
    {
        return new self('Cannot update an exam enrollment under a Cancelled session.', 'exams.validation.session_cancelled_for_enrollment_update');
    }

    public static function sessionNotInProgressForPresent(): self
    {
        return new self('Marking an exam enrollment Present requires the exam session to be InProgress.', 'exams.validation.session_not_in_progress_for_present');
    }

    public static function gradeLookupFailed(): self
    {
        return new self('Exam enrollment grade state could not be determined.', 'exams.validation.grade_lookup_failed');
    }
}
