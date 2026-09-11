<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamCancelBlockedException extends DomainException
{
    public static function currentGradeExists(): self
    {
        return new self('CancelExam is blocked because a current grade exists (DR-001).');
    }

    public static function completedSessionExists(): self
    {
        return new self('CancelExam is blocked because a Completed session exists (DR-001).');
    }

    public static function examCompleted(): self
    {
        return new self('CancelExam is blocked because the exam is already Completed (DR-001).');
    }

    public static function examAlreadyCancelled(): self
    {
        return new self('CancelExam is blocked because the exam is already Cancelled (DR-001).');
    }

    public static function sessionCurrentGradeExists(): self
    {
        return new self('CancelExamSession is blocked because a CURRENT grade exists for the session (HD-7.2-003).');
    }

    public static function sessionCompleted(): self
    {
        return new self('CancelExamSession is blocked because the session is already Completed.');
    }

    public static function enrollmentCurrentGradeExists(): self
    {
        return new self('CancelExamEnrollment is blocked because a CURRENT grade exists for the enrollment (DR-002).');
    }
}
