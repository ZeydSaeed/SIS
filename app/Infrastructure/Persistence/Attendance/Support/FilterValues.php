<?php

namespace App\Infrastructure\Persistence\Attendance\Support;

/** Filter values from a single id, a comma list or an array (multi-select ribbon filters) → positive ints. */
final class FilterValues
{
    /** Code columns stored as SMALLINT (a larger value would be an SQL error, not an empty result). */
    public const SMALL = ['status', 'sheet_status', 'lesson_type', 'origin', 'excuse_type', 'severity'];

    /** INTEGER / BIGINT ids beyond this do not exist; dropping them keeps every bound value in range. */
    public const MAX_ID = 2147483647;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    public static function ids(array $filters, string $key): array
    {
        $raw = $filters[$key] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        $max = in_array($key, self::SMALL, true) ? 32767 : self::MAX_ID;

        return array_values(array_unique(array_filter(array_map('intval', $values), static fn (int $v): bool => $v > 0 && $v <= $max)));
    }
}
