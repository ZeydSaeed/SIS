<?php

namespace App\Domain\Communication\ValueObjects;

final class NotificationChannel
{
    public const Email = 1;

    public const Sms = 2;

    public const InApp = 3;

    public const Other = 9;

    public static function isValid(int $channel): bool
    {
        return in_array($channel, [self::Email, self::Sms, self::InApp, self::Other], true);
    }
}
