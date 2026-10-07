<?php

namespace App\Domain\Timetable\ValueObjects;

/**
 * A timetable version (snapshot of the whole school-year grid):
 *
 * draft ──submit──▶ review ──workflow approve──▶ approved ──publish──▶ published ──newer publish──▶ superseded
 *   │                  └──workflow reject──▶ rejected                                         any non-published ──▶ archived
 *   └─ archived
 *
 * «Active» is not stored: the published version whose dates contain today.
 */
enum TimetableVersionStatus: int
{
    case Draft = 1;
    case Review = 2;
    case Approved = 3;
    case Rejected = 4;
    case Published = 5;
    case Superseded = 6;
    case Archived = 7;

    public function canSubmit(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    public function canPublish(): bool
    {
        return $this === self::Approved;
    }

    public function canArchive(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Approved, self::Superseded], true);
    }
}
