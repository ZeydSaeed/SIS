<?php

namespace App\Http\Support;

use Illuminate\Http\Request;

/**
 * Query-string filters of the attendance screens (Home-tab ribbon filters): id lists (`?teacher_id[]=3&teacher_id[]=5`
 * or `?teacher_id=3,5`), Y-m-d dates and flags. Anything malformed is dropped — never passed to a query.
 */
final class AttendanceFilterInput
{
    /**
     * @param  list<string>  $listKeys
     * @param  list<string>  $dateKeys
     * @param  list<string>  $flagKeys
     * @return array<string, mixed>
     */
    public static function from(Request $request, array $listKeys, array $dateKeys = ['date_from', 'date_to'], array $flagKeys = []): array
    {
        $filters = [];
        foreach ($listKeys as $key) {
            $filters[$key] = self::ids($request->query($key));
        }
        foreach ($dateKeys as $key) {
            $value = $request->query($key);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $filters[$key] = $value;
            }
        }
        foreach ($flagKeys as $key) {
            $filters[$key] = $request->boolean($key);
        }

        return $filters;
    }

    /**
     * Review board / sessions list filters.
     *
     * @param  list<int>  $defaultStatuses
     * @return array<string, mixed>
     */
    public static function board(Request $request, array $defaultStatuses): array
    {
        return self::from($request, ['teacher_id', 'subject_id', 'section_id', 'class_id', 'lesson_type', 'origin'], ['date_from', 'date_to'], ['late'])
            + ['sheet_status' => self::statuses($request, 'sheet_status', $defaultStatuses)];
    }

    /**
     * A status list with a default: absent parameter → $default; present but empty (`?status=`) → all (empty list).
     *
     * @param  list<int>  $default
     * @return list<int>
     */
    public static function statuses(Request $request, string $key, array $default): array
    {
        return $request->has($key) ? self::ids($request->query($key)) : $default;
    }

    /**
     * @return list<int>
     */
    public static function ids(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map(static fn ($v): int => is_scalar($v) && ctype_digit((string) $v) ? (int) $v : 0, $values), static fn (int $v): bool => $v > 0)));
    }
}
