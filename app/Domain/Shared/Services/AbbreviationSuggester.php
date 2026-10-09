<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\ValueObjects\DisplayAppearance;

/**
 * Proposes an abbreviation from a name when the user has not set one (the user can always replace it).
 *
 * - person   the given name; compound names keep their prefix («عبد الأمير», «نور الهدى» stays whole
 *            when it is the given name of two words joined by «ال»).
 * - label    short labels stay as they are; several words → their initials joined by «.» («مبادئ حاسوب» → «م.ح»);
 *            one long word → its first letters.
 */
final class AbbreviationSuggester
{
    public const PERSON = 'person';

    public const LABEL = 'label';

    private const SHORT_LABEL = 8;

    private const LONG_WORD_LETTERS = 5;

    /** Arabic name prefixes that never stand alone. */
    private const PREFIXES = ['عبد', 'ابو', 'أبو', 'بن', 'ابن', 'آل'];

    public function suggest(string $name, string $kind = self::LABEL): ?string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return null;
        }

        $suggestion = $kind === self::PERSON ? self::person($words) : self::label($words);

        return mb_substr($suggestion, 0, DisplayAppearance::MAX_ABBREVIATION);
    }

    /** @param  list<string>  $words */
    private static function person(array $words): string
    {
        $first = $words[0];
        $second = $words[1] ?? null;
        if ($second !== null && (in_array($first, self::PREFIXES, true) || str_starts_with($second, 'ال'))) {
            // «عبد الأمير», «نور الهدى», «سيف الدين»: a compound given name.
            return $first.' '.$second;
        }

        return $first;
    }

    /** @param  list<string>  $words */
    private static function label(array $words): string
    {
        $joined = implode(' ', $words);
        if (mb_strlen($joined) <= self::SHORT_LABEL) {
            return $joined;
        }
        if (count($words) > 1) {
            return implode('.', array_map(static fn (string $w): string => mb_substr(self::withoutArticle($w), 0, 1), $words));
        }

        return mb_substr($joined, 0, self::LONG_WORD_LETTERS);
    }

    private static function withoutArticle(string $word): string
    {
        return mb_strlen($word) > 3 && str_starts_with($word, 'ال') ? mb_substr($word, 2) : $word;
    }
}
