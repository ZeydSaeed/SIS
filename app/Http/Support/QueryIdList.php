<?php

namespace App\Http\Support;

use Illuminate\Http\Request;

/**
 * One reading of a multi-value filter from the query string: `class_id=1,2,3` (or the older single `class_id=5`).
 * The ribbon filters of every page send their choices this way; nothing here widens what a user may see — callers
 * still scope by school / academic year.
 */
final class QueryIdList
{
    public const MAX = 50;

    /** @return list<int> positive, de-duplicated, at most {@see self::MAX} */
    public static function from(Request $request, string $key): array
    {
        $raw = $request->query($key);
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '' && ctype_digit($part) && (int) $part > 0) {
                $ids[(int) $part] = (int) $part;
            }
        }

        return array_slice(array_values($ids), 0, self::MAX);
    }
}
