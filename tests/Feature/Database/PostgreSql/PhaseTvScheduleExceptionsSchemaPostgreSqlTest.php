<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvScheduleExceptionsSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function schedule_exceptions_exist_with_force_rls(): void
    {
        $exists = DB::selectOne("
            SELECT 1 AS ok FROM information_schema.tables
            WHERE table_schema = 'timetable' AND table_name = 'schedule_exceptions'
        ");
        $this->assertNotNull($exists);

        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'timetable' AND table_name = 'schedule_exceptions'
        "))->pluck('column_name')->all();
        $this->assertContains('school_id', $cols);
        $this->assertContains('exception_date', $cols);
        $this->assertContains('substitute_teacher_id', $cols);

        $rls = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'timetable' AND c.relname = 'schedule_exceptions'
        ");
        $this->assertTrue((bool) $rls->rls);
        $this->assertTrue((bool) $rls->force_rls);
    }
}
