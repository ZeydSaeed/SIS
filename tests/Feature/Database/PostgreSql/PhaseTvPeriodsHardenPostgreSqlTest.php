<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

final class PhaseTvPeriodsHardenPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function periods_force_rls_and_reject_delete_trigger_exist(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'timetable' AND c.relname = 'periods'
        ");

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);

        $trigger = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'timetable'
              AND c.relname = 'periods'
              AND t.tgname = 'periods_reject_delete'
              AND NOT t.tgisinternal
        ");
        $this->assertNotNull($trigger);
    }

    #[Test]
    public function periods_rls_isolates_schools_and_rejects_delete(): void
    {
        $schoolA = $this->createSchool('SCH-TV-U01A', 'TV Periods A');
        $schoolB = $this->createSchool('SCH-TV-U01B', 'TV Periods B');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $periodA = (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId([
            'school_id' => $schoolA,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $periodB = (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId([
            'school_id' => $schoolB,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);

        PostgreSqlRlsActor::become();

        try {
            DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
            $visible = collect(DB::select('SELECT id FROM timetable.periods'))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $this->assertSame([$periodA], $visible);
            $this->assertNotContains($periodB, $visible);

            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::table(SchemaHelper::qualified('timetable', 'periods'))->where('id', $periodA)->delete();
        } finally {
            PostgreSqlRlsActor::reset();
        }
    }

    #[Test]
    public function periods_pk_remains_smallint_for_attendance_contract(): void
    {
        $col = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = 'timetable' AND table_name = 'periods' AND column_name = 'id'
        ");
        $this->assertNotNull($col);
        $this->assertSame('smallint', $col->data_type);
    }
}
