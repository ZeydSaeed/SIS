<?php

namespace App\Domain\Documents\Support;

final class DocumentEntityTypes
{
    public const ALLOWED = [
        'student',
        'teacher',
        'enrollment',
        'certificate',
        'qualification',
    ];

    public static function isAllowed(string $entityType): bool
    {
        return in_array(strtolower(trim($entityType)), self::ALLOWED, true);
    }
}
