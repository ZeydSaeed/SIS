<?php

namespace App\Domain\Leave\ValueObjects;

/** leave.types.applies_to — which staff may use a leave type (teacher and employee rules are kept apart). */
enum LeaveAudience: int
{
    case Teachers = 1;
    case Employees = 2;
    case All = 3;

    public function allows(RequesterKind $kind): bool
    {
        return $this === self::All
            || ($this === self::Teachers && $kind === RequesterKind::Teacher)
            || ($this === self::Employees && $kind === RequesterKind::Employee);
    }
}
