<?php

namespace App\Domain\Communication\Support;

final class MessageRecipientTypes
{
    public const ALLOWED = ['student', 'teacher', 'user', 'guardian'];

    public static function isAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED, true);
    }
}
