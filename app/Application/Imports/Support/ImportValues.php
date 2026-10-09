<?php

namespace App\Application\Imports\Support;

/**
 * Cell parsing shared by the import profiles: Arabic / English yes-no and status words, dates (ISO, d/m/Y and Excel
 * serial numbers), integers, and header matching (spaces, «*», «_», case and Arabic letter forms ignored).
 */
final class ImportValues
{
    public static function header(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['*', '_', '-', '(', ')', 'ـ', ' '], '', $value);

        return strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
    }

    /** null = empty; true / false = understood; 'invalid' = not a yes/no. */
    public static function bool(string $value): bool|string|null
    {
        $v = self::header($value);
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['1', 'نعم', 'yes', 'true', 'y', 'صح', 'نشط', 'فعال', 'active'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'لا', 'no', 'false', 'n', 'خطا', 'غيرنشط', 'معطل', 'inactive'], true)) {
            return false;
        }

        return 'invalid';
    }

    /** 1 active · 2 inactive (default active); null = not understood. */
    public static function status(string $value): ?int
    {
        $bool = self::bool($value);

        return $bool === null || $bool === true ? 1 : ($bool === false ? 2 : null);
    }

    public static function int(string $value, int $min, int $max): ?int
    {
        $value = strtr(trim($value), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        if (! preg_match('/^-?\d+(\.0+)?$/', $value)) {
            return null;
        }
        $int = (int) $value;

        return $int < $min || $int > $max ? null : $int;
    }

    /** 'Y-m-d' or null (empty / not a date). Accepts 2024-09-01, 1/9/2024, 01-09-2024 and Excel serials. */
    public static function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $value) === 1 && (float) $value > 20000 && (float) $value < 80000) {
            return (new \DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    public static function nullable(string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    /** @return list<string> a «،» / «,» / «;» separated list */
    public static function list(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[،,;|]/u', $value) ?: []), static fn (string $v): bool => $v !== ''));
    }
}
