<?php

namespace App\Domain\Communication\ValueObjects;

final class NotificationJobStatus
{
    public const Open = 1;

    public const Completed = 2;

    public const Cancelled = 3;

    public static function isValid(int $status): bool
    {
        return in_array($status, [self::Open, self::Completed, self::Cancelled], true);
    }
}
