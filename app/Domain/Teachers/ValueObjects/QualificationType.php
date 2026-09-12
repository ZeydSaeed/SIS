<?php

namespace App\Domain\Teachers\ValueObjects;

final class QualificationType
{
    public const Degree = 1;

    public const Certificate = 2;

    public const License = 3;

    public const Other = 9;

    public static function isValid(int $type): bool
    {
        return in_array($type, [self::Degree, self::Certificate, self::License, self::Other], true);
    }
}
