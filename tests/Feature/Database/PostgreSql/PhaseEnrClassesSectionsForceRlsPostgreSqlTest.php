<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

final class PhaseEnrClassesSectionsForceRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function classes_and_sections_have_force_rls_and_reject_hard_delete(): void
    {
        foreach (['classes', 'sections'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'enrollment' AND c.relname = ?
            ", [$table]);
            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls, $table);
            $this->assertTrue((bool) $row->force_rls, $table);
        }

        foreach ([
            'classes' => 'enrollment_classes_reject_hard_delete',
            'sections' => 'enrollment_sections_reject_hard_delete',
        ] as $table => $trigger) {
            $found = DB::selectOne("
                SELECT 1 AS ok FROM pg_trigger t
                JOIN pg_class c ON c.oid = t.tgrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'enrollment' AND c.relname = ?
                  AND t.tgname = ? AND NOT t.tgisinternal
            ", [$table, $trigger]);
            $this->assertNotNull($found, $trigger);
        }
    }

    #[Test]
    public function classes_and_sections_rls_isolates_schools_fail_closed(): void
    {
        $schoolA = $this->createSchool('SCH-ENR2A', 'ENR2 A');
        $schoolB = $this->createSchool('SCH-ENR2B', 'ENR2 B');
        $yearId = $this->createAcademicYear('AY-ENR2');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $classA = $this->createClassForSchool($schoolA, $yearId);
        $sectionA = $this->createSectionForClass((int) $classA->id);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $classB = $this->createClassForSchool($schoolB, $yearId);
        $sectionB = $this->createSectionForClass((int) $classB->id);

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $classIdsA = collect(DB::select('SELECT id FROM enrollment.classes'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sectionIdsA = collect(DB::select('SELECT id FROM enrollment.sections'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([(int) $classA->id], $classIdsA);
        $this->assertSame([(int) $sectionA->id], $sectionIdsA);
        $this->assertNotContains((int) $classB->id, $classIdsA);
        $this->assertNotContains((int) $sectionB->id, $sectionIdsA);

        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM enrollment.classes')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM enrollment.sections')->c);

        PostgreSqlRlsActor::reset();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $this->expectException(QueryException::class);
        DB::table(SchemaHelper::qualified('enrollment', 'sections'))->where('id', $sectionA->id)->delete();
    }
}
