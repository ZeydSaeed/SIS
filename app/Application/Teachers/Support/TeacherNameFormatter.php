<?php

namespace App\Application\Teachers\Support;

/** الاسم الثلاثي واللقب: first · father · grandfather · last (empty parts skipped). */
final class TeacherNameFormatter
{
    public static function fullName(string $first, ?string $father, ?string $grandfather, string $last): string
    {
        $parts = array_filter(
            [trim($first), trim((string) $father), trim((string) $grandfather), trim($last)],
            static fn (string $part): bool => $part !== '',
        );

        return implode(' ', $parts);
    }

    public static function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
