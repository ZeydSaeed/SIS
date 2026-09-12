<?php

namespace App\Domain\Finance\ValueObjects;

final class StudentFeeStatus
{
    public const Unpaid = 1;

    public const Partial = 2;

    public const Paid = 3;

    public const Cancelled = 4;

    public static function isValid(int $status): bool
    {
        return in_array($status, [self::Unpaid, self::Partial, self::Paid, self::Cancelled], true);
    }
}
