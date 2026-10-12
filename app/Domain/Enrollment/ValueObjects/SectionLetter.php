<?php

namespace App\Domain\Enrollment\ValueObjects;

/**
 * «الشعبة» — one source of truth: a section is named by its letter A, B or C (code `SEC-A` …), the same
 * values as the UI pick-list (`SIS_SECTION_OPTIONS`). Arabic spellings typed or imported (أ / ب / ج, «شعبة أ»)
 * are accepted as input and normalised to the letter.
 */
final class SectionLetter
{
    public const ALL = ['A', 'B', 'C'];

    private const ALIASES = [
        'A' => 'A', 'أ' => 'A', 'ا' => 'A', 'إ' => 'A', 'آ' => 'A',
        'B' => 'B', 'ب' => 'B',
        'C' => 'C', 'ج' => 'C',
    ];

    /** The letter for a typed / imported name, or null when it is not a valid section name. */
    public static function normalize(string $name): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $name));
        $value = trim((string) preg_replace('/^(شعبة|الشعبة|section|sec-?)\s*/iu', '', $value));
        $value = mb_strtoupper($value);

        return self::ALIASES[$value] ?? null;
    }

    public static function code(string $letter): string
    {
        return 'SEC-'.$letter;
    }
}
