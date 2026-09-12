<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase75RankingSnapshotsSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function ranking_snapshot_tables_exist_with_force_rls(): void
    {
        foreach (['ranking_snapshots', 'ranking_snapshot_entries'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'results' AND c.relname = ?
            ", [$table]);

            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls_enabled, $table);
            $this->assertTrue((bool) $row->rls_forced, $table);
        }

        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'results' AND table_name = 'ranking_snapshots'
        "))->pluck('column_name')->all();

        $this->assertContains('class_id', $cols);
        $this->assertContains('metric_code', $cols);
        $this->assertNotContains('rank_in_section', $cols);
    }
}
