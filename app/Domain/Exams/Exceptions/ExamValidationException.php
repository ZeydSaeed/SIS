<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamValidationException extends DomainException
{
    public static function invalidDates(): self
    {
        return new self('Exam end_date must be greater than or equal to start_date.');
    }

    public static function missingAcademicYear(): self
    {
        return new self('Academic year does not exist.');
    }

    public static function missingTerm(): self
    {
        return new self('Term does not exist for the academic year.');
    }

    public static function missingExamType(): self
    {
        return new self('Exam type does not exist.');
    }

    public static function missingIdempotencyKey(): self
    {
        return new self('Idempotency key is required for exam administration commands.');
    }

    public static function emptyUpdate(): self
    {
        return new self('UpdateExam requires at least one allowlisted field or a status transition.');
    }

    public static function parentExamCancelled(): self
    {
        return new self('Cannot create an exam session under a Cancelled exam.');
    }

    public static function parentExamNotFound(): self
    {
        return new self('Exam does not exist in the current school.');
    }

    public static function missingSubject(): self
    {
        return new self('Subject does not exist.');
    }

    public static function invalidSessionTimeRange(): self
    {
        return new self('Session end_time must be greater than start_time.');
    }

    public static function invalidPassMaxGrade(): self
    {
        return new self('pass_grade must be between 0 and max_grade.');
    }

    public static function roomNotInSchool(): self
    {
        return new self('Room does not belong to the current school.');
    }

    public static function missingIdempotencyKeyForSession(): self
    {
        return new self('Idempotency key is required for exam session commands.');
    }

    public static function emptySessionUpdate(): self
    {
        return new self('UpdateExamSession requires at least one allowlisted field.');
    }

    public static function sessionNotFound(): self
    {
        return new self('Exam session does not exist in the current school.');
    }

    public static function parentExamCancelledForOpen(): self
    {
        return new self('Cannot open an exam session under a Cancelled exam.');
    }

    public static function parentExamCancelledForClose(): self
    {
        return new self('Cannot close an exam session under a Cancelled exam.');
    }

    public static function missingIdempotencyKeyForEnrollment(): self
    {
        return new self('Idempotency key is required for exam enrollment commands.');
    }

    public static function academicEnrollmentNotFound(): self
    {
        return new self('Academic enrollment does not exist in the current school.');
    }

    public static function academicYearMismatchForEnrollment(): self
    {
        return new self('Academic enrollment academic_year_id must equal the exam academic_year_id.');
    }

    public static function sessionCancelledForEnrollment(): self
    {
        return new self('Cannot create an exam enrollment under a Cancelled session.');
    }

    public static function duplicateExamEnrollment(): self
    {
        return new self('An exam enrollment already exists for this session and academic enrollment.');
    }

    public static function emptyEnrollmentUpdate(): self
    {
        return new self('UpdateExamEnrollment requires a meaningful status transition or seat_number change.');
    }

    public static function examEnrollmentNotFound(): self
    {
        return new self('Exam enrollment does not exist in the current school.');
    }

    public static function sessionCancelledForEnrollmentUpdate(): self
    {
        return new self('Cannot update an exam enrollment under a Cancelled session.');
    }

    public static function sessionNotInProgressForPresent(): self
    {
        return new self('Marking an exam enrollment Present requires the exam session to be InProgress.');
    }

    public static function gradeLookupFailed(): self
    {
        return new self('Exam enrollment grade state could not be determined.');
    }
}
