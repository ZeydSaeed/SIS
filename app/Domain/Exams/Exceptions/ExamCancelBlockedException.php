<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ExamCancelBlockedException extends SisDomainException
{
    public static function currentGradeExists(): self
    {
        return new self('CancelExam is blocked because a current grade exists (DR-001).', 'exams.cancel.current_grade_exists');
    }

    public static function completedSessionExists(): self
    {
        return new self('CancelExam is blocked because a Completed session exists (DR-001).', 'exams.cancel.completed_session_exists');
    }

    public static function examCompleted(): self
    {
        return new self('CancelExam is blocked because the exam is already Completed (DR-001).', 'exams.cancel.exam_completed');
    }

    public static function examAlreadyCancelled(): self
    {
        return new self('CancelExam is blocked because the exam is already Cancelled (DR-001).', 'exams.cancel.exam_already_cancelled');
    }

    public static function sessionCurrentGradeExists(): self
    {
        return new self('CancelExamSession is blocked because a CURRENT grade exists for the session (HD-7.2-003).', 'exams.cancel.session_current_grade_exists');
    }

    public static function sessionCompleted(): self
    {
        return new self('CancelExamSession is blocked because the session is already Completed.', 'exams.cancel.session_completed');
    }

    public static function enrollmentCurrentGradeExists(): self
    {
        return new self('CancelExamEnrollment is blocked because a CURRENT grade exists for the enrollment (DR-002).', 'exams.cancel.enrollment_current_grade_exists');
    }
}
