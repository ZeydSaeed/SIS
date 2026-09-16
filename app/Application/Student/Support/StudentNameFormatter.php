<?php

namespace App\Application\Student\Support;

final class StudentNameFormatter
{
    /**
     * Arabic civil display name: given + father + grandfather + great-grandfather + family (لقب).
     * Falls back to legacy middle_name when father_name is empty.
     */
    public static function fullName(
        string $firstName,
        string $lastName,
        ?string $fatherName = null,
        ?string $grandfatherName = null,
        ?string $greatGrandfatherName = null,
        ?string $middleName = null,
    ): string {
        $parts = [$firstName];

        if ($fatherName !== null && $fatherName !== '') {
            $parts[] = $fatherName;
        } elseif ($middleName !== null && $middleName !== '') {
            $parts[] = $middleName;
        }

        if ($grandfatherName !== null && $grandfatherName !== '') {
            $parts[] = $grandfatherName;
        }

        if ($greatGrandfatherName !== null && $greatGrandfatherName !== '') {
            $parts[] = $greatGrandfatherName;
        }

        $parts[] = $lastName;

        return trim(implode(' ', $parts));
    }
}
