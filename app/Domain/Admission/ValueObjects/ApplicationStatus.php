<?php

namespace App\Domain\Admission\ValueObjects;

/**
 * Application lifecycle statuses (admission.applications.status).
 * Waitlist is a status — no separate waitlist table in Phase 2 blueprint.
 */
enum ApplicationStatus: int
{
    case Draft = 1;
    case Submitted = 2;
    case UnderReview = 3;
    case Interview = 4;
    case Waitlisted = 5;
    case Accepted = 6;
    case Rejected = 7;
    case Withdrawn = 8;
    case Converted = 9;

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Rejected, self::Withdrawn, self::Converted => true,
            default => false,
        };
    }

    public function canConvertToStudent(): bool
    {
        return $this === self::Accepted;
    }
}
