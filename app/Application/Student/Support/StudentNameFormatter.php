<?php

namespace App\Application\Student\Support;

final class StudentNameFormatter
{
    public static function fullName(string $firstName, ?string $middleName, string $lastName): string
    {
        return trim(implode(' ', array_filter([$firstName, $middleName, $lastName], fn (?string $part): bool => $part !== null && $part !== '')));
    }
}
