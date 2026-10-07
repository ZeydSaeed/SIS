<?php

namespace App\Domain\Timetable\ValueObjects;

/** A generation run: queued → running → succeeded | failed | cancelled; a succeeded run is applied or discarded. */
enum GenerationRunStatus: int
{
    case Queued = 1;
    case Running = 2;
    case Succeeded = 3;
    case Failed = 4;
    case Cancelled = 5;
    case Applied = 6;
    case Discarded = 7;

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
