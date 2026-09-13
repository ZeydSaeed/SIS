<?php

namespace App\Domain\Hr\ValueObjects;

final class JobPositionCategory
{
    public const Administrative = 1;

    public const Teaching = 2;

    public const Technical = 3;

    public const Support = 4;

    public const Other = 9;

    public static function isValid(int $category): bool
    {
        return in_array($category, [
            self::Administrative,
            self::Teaching,
            self::Technical,
            self::Support,
            self::Other,
        ], true);
    }
}
