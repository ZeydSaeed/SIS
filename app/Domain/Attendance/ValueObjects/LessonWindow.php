<?php

namespace App\Domain\Attendance\ValueObjects;

/** Where «now» falls relative to a lesson, under the governing attendance policy. */
enum LessonWindow: string
{
    /** Before start − open_before_minutes: the sheet cannot be opened by the teacher yet. */
    case NotYetOpen = 'not_yet_open';
    /** From the opening time until lesson end + submit_deadline_minutes. */
    case Open = 'open';
    /** After the deadline but within edit_window_days: still allowed, the submission is flagged late. */
    case LateSubmission = 'late';
    /** After the edit window: only an authorised reviewer may act. */
    case Closed = 'closed';

    public function teacherMayWork(): bool
    {
        return $this === self::Open || $this === self::LateSubmission;
    }
}
