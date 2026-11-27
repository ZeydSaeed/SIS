<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;

/**
 * «حذف آمن» — the no-delete triggers stay, with one narrow exception: a DELETE inside a transaction that set
 * `sis.safe_delete = on` (only PostgresSafeDeleter does) is let through. Any other DELETE is still refused.
 *
 * Works on the BEFORE DELETE trigger functions of the given tables: one guard line is added right after the
 * function's first BEGIN (idempotent), or removed again by uninstall().
 */
final class SafeDeleteTriggerBypass
{
    public const MARKER = '/* sis.safe_delete */';

    private const GUARD = self::MARKER." IF TG_OP = 'DELETE' AND current_setting('sis.safe_delete', true) = 'on' THEN RETURN OLD; END IF;";

    /** @param  list<string>  $tables  qualified table names */
    public static function install(array $tables): void
    {
        foreach (self::functions($tables) as $oid) {
            $def = (string) DB::selectOne('SELECT pg_get_functiondef(?::oid) d', [$oid])->d;
            if (str_contains($def, self::MARKER)) {
                continue;
            }
            $patched = preg_replace('/\bBEGIN\b/i', "BEGIN\n    ".self::GUARD, $def, 1);
            if ($patched === null || $patched === $def) {
                throw new \RuntimeException('Cannot add the safe-delete guard to trigger function '.$oid);
            }
            DB::unprepared($patched);
        }
    }

    /** @param  list<string>  $tables */
    public static function uninstall(array $tables): void
    {
        foreach (self::functions($tables) as $oid) {
            $def = (string) DB::selectOne('SELECT pg_get_functiondef(?::oid) d', [$oid])->d;
            if (! str_contains($def, self::MARKER)) {
                continue;
            }
            DB::unprepared(str_replace("\n    ".self::GUARD, '', $def));
        }
    }

    /**
     * Trigger functions that fire on DELETE for the given tables (each once, even when shared).
     *
     * @param  list<string>  $tables
     * @return list<int>
     */
    private static function functions(array $tables): array
    {
        $oids = [];
        foreach ($tables as $table) {
            if (DB::selectOne('SELECT to_regclass(?) r', [$table])->r === null) {
                continue;
            }
            // tgtype bit 3 (8) = DELETE.
            foreach (DB::select('SELECT DISTINCT tgfoid::int f FROM pg_trigger WHERE tgrelid = ?::regclass AND NOT tgisinternal AND (tgtype & 8) <> 0', [$table]) as $row) {
                $oids[(int) $row->f] = (int) $row->f;
            }
        }

        return array_values($oids);
    }
}
