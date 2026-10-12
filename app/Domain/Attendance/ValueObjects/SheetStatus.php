<?php

namespace App\Domain\Attendance\ValueObjects;

/**
 * attendance.sessions.sheet_status — the review lifecycle of an attendance sheet («كشف الحضور»).
 *
 *   Draft ──submit──▶ Submitted ──start review──▶ UnderReview ──approve──▶ Approved
 *     ▲                   │                            │
 *     │                   ├──────── approve ───────────┼──▶ Approved
 *     └──── Returned ◀────┴──── return (reason) ◀──────┤
 *                                                      └──▶ Rejected (reason)
 *
 * A returned sheet is corrected by its teacher and resubmitted (submission_count > 1).
 */
enum SheetStatus: int
{
    case Draft = 1;
    case Submitted = 2;
    case UnderReview = 3;
    case Approved = 4;
    case Returned = 5;
    case Rejected = 6;

    /** The teacher may still change marks. */
    public function allowsTeacherEditing(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    public function awaitsReview(): bool
    {
        return $this === self::Submitted || $this === self::UnderReview;
    }

    public function isFinal(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }
}
