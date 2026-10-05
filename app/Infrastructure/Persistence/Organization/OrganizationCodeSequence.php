<?php

namespace App\Infrastructure\Persistence\Organization;

use Illuminate\Support\Facades\DB;

/**
 * System codes for organization records (SCH-0001, DIR-0001, …) — users never type codes.
 * Must run inside the creating transaction: the advisory lock serializes concurrent creators
 * until commit, so two inserts cannot pick the same next number.
 */
final class OrganizationCodeSequence
{
    public static function next(string $table, string $prefix): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$table.'.code']);
        }

        $pattern = '^'.$prefix.'-[0-9]+$';
        $max = 0;
        foreach (DB::table($table)->whereRaw('code ~ ?', [$pattern])->pluck('code') as $code) {
            $max = max($max, (int) substr((string) $code, strlen($prefix) + 1));
        }

        return sprintf('%s-%04d', $prefix, $max + 1);
    }
}
