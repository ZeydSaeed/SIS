<?php

namespace App\Infrastructure\Persistence\Attendance\Support;

/** Filter values from a single id, a comma list or an array (multi-select ribbon filters) → positive ints. */
final class FilterValues
{
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

        return array_values(array_unique(array_filter(array_map('intval', $values), static fn (int $v): bool => $v > 0)));
    }
}
