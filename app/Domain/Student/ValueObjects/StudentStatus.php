<?php

namespace App\Domain\Student\ValueObjects;

enum StudentStatus: int
{
    case Inactive = 0;
    case Active = 1;
    case Suspended = 2;
    case Graduated = 3;
    case Withdrawn = 4;

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canEnroll(): bool
    {
        return $this === self::Active;
    }
}
