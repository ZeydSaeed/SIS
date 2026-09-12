<?php

namespace App\Domain\Finance\ValueObjects;

final class FeeTypeStatus
{
    public const Active = 1;

    public const Inactive = 2;

    public static function isValid(int $status): bool
    {
        return in_array($status, [self::Active, self::Inactive], true);
    }
}
