<?php

namespace App\Http\Support;

use App\Domain\Attendance\ValueObjects\CalendarDate;
use Illuminate\Http\Request;

/**
 * Query-string filters of the attendance screens (Home-tab ribbon filters): id lists (`?teacher_id[]=3&teacher_id[]=5`
 * or `?teacher_id=3,5`), Y-m-d dates and flags. Anything malformed is dropped — never passed to a query.
 */
final class AttendanceFilterInput
{
    /** Code filters stored as SMALLINT (status, type …): larger values are dropped, never bound to a query. */
    private const SMALL = ['status', 'sheet_status', 'lesson_type', 'origin', 'excuse_type', 'severity'];

    private const MAX_ID = 2147483647;

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
            $filters[$key] = self::ids($request->query($key), in_array($key, self::SMALL, true) ? 32767 : self::MAX_ID);
        }
        foreach ($dateKeys as $key) {
            $value = $request->query($key);
            if (CalendarDate::valid($value)) {
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
        return $request->has($key) ? self::ids($request->query($key), 32767) : $default;
    }

    /** A free-text query parameter (search box): a trimmed string of at most `$max` characters; arrays → ''. */
    public static function text(Request $request, string $key, int $max = 100): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    /** A Y-m-d query parameter that exists in the calendar, else null (never reaches a query). */
    public static function date(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return CalendarDate::valid($value) ? (string) $value : null;
    }

    /** Page size: the table's fit-to-height `per_page` (3…100), else the configured default of the list. */
    public static function perPage(Request $request, string $list, int $default): int
    {
        $raw = $request->query('per_page');
        if (is_string($raw) && ctype_digit($raw)) {
            return max(3, min(100, (int) $raw));
        }

        return (int) config("attendance.per_page.{$list}", $default);
    }

    /**
     * @return list<int>
     */
    public static function ids(mixed $raw, int $max = self::MAX_ID): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map(static fn ($v): int => is_scalar($v) && ctype_digit((string) $v) ? (int) $v : 0, $values), static fn (int $v): bool => $v > 0 && $v <= $max)));
    }
}
