<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvSchedulesSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function schedules_table_exists_with_school_id_force_rls_and_reject_delete(): void
    {
        $exists = DB::selectOne("
            SELECT 1 AS ok
            FROM information_schema.tables
            WHERE table_schema = 'timetable' AND table_name = 'schedules'
        ");
        $this->assertNotNull($exists);

        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'timetable' AND table_name = 'schedules'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);
        $this->assertContains('lifecycle_status', $cols);
        $this->assertContains('cancelled_at', $cols);

        $rls = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'timetable' AND c.relname = 'schedules'
        ");
        $this->assertTrue((bool) $rls->rls);
        $this->assertTrue((bool) $rls->force_rls);

        $trigger = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'timetable'
              AND c.relname = 'schedules'
              AND t.tgname = 'schedules_reject_delete'
              AND NOT t.tgisinternal
        ");
        $this->assertNotNull($trigger);

        $uniques = collect(DB::select("
            SELECT indexname FROM pg_indexes
            WHERE schemaname = 'timetable' AND tablename = 'schedules'
        "))->pluck('indexname')->all();

        $this->assertContains('schedules_section_slot_active_uidx', $uniques);
        $this->assertContains('schedules_teacher_slot_active_uidx', $uniques);
        $this->assertContains('schedules_room_slot_active_uidx', $uniques);
    }
}
