<?php

namespace Tests\Support\Optimization;

use Illuminate\Support\Facades\DB;

final class PostgreSqlAnalyzeEvidence
{
    public const VALIDATION_SCHEMA = 'intelligence';

    public const VALIDATION_TABLE = 'optimization_validation_target';

    public const QUALIFIED_TARGET = 'intelligence.optimization_validation_target';

    /**
     * @return array<string, mixed>|null
     */
    public static function tableStats(): ?array
    {
        $row = DB::selectOne('
            SELECT
                schemaname,
                relname,
                n_live_tup AS row_estimate,
                last_analyze,
                last_autoanalyze
            FROM pg_stat_user_tables
            WHERE schemaname = ? AND relname = ?
        ', [self::VALIDATION_SCHEMA, self::VALIDATION_TABLE]);

        return $row ? (array) $row : null;
    }

    public static function ensureValidationTableSeeded(): void
    {
        $qualified = self::QUALIFIED_TARGET;
        DB::statement("CREATE SCHEMA IF NOT EXISTS intelligence");
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$qualified} (
                id BIGSERIAL PRIMARY KEY,
                label VARCHAR(100) NOT NULL,
                payload INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $count = (int) DB::table($qualified)->count();
        if ($count < 500) {
            $rows = [];
            for ($i = $count; $i < 500; $i++) {
                $rows[] = ['label' => 'validation-row-'.$i, 'payload' => $i];
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($qualified)->insert($chunk);
            }
        }

        DB::statement('ANALYZE '.$qualified);
    }
}
