<?php

namespace App\Domain\Student\ValueObjects;

final class StudentDocumentType
{
    public const Identity = 1;

    public const Certificate = 2;

    public const Qualification = 3;

    public const Medical = 4;

    public const Other = 9;

    public static function isValid(int $type): bool
    {
        return in_array($type, [
            self::Identity,
            self::Certificate,
            self::Qualification,
            self::Medical,
            self::Other,
        ], true);
    }
}
