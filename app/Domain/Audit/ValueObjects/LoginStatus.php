<?php

namespace App\Domain\Audit\ValueObjects;

final class LoginStatus
{
    public const Success = 1;

    public const Failed = 2;

    public static function isValid(int $status): bool
    {
        return in_array($status, [self::Success, self::Failed], true);
    }
}
