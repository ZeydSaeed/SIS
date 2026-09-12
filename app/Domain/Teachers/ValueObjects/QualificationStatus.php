<?php

namespace App\Domain\Teachers\ValueObjects;

final class QualificationStatus
{
    public const Active = 1;

    public const Voided = 2;

    public static function isActive(int $status): bool
    {
        return $status === self::Active;
    }
}
