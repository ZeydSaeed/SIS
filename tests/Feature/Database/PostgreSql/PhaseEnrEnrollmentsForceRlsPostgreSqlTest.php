<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

final class PhaseEnrEnrollmentsForceRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function enrollments_have_force_rls_and_reject_hard_delete(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'enrollment' AND c.relname = 'enrollments'
        ");
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);

        $trigger = DB::selectOne("
            SELECT 1 AS ok FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'enrollment' AND c.relname = 'enrollments'
              AND t.tgname = 'enrollment_enrollments_reject_hard_delete' AND NOT t.tgisinternal
        ");
        $this->assertNotNull($trigger);
    }

    #[Test]
    public function enrollments_rls_isolates_schools_and_fails_closed_without_guc(): void
    {
        $schoolA = $this->createSchool('SCH-ENR1A', 'ENR School A');
        $schoolB = $this->createSchool('SCH-ENR1B', 'ENR School B');
        $yearId = $this->createAcademicYear('AY-ENR1');
        $classA = $this->createClassForSchool($schoolA, $yearId);
        $classB = $this->createClassForSchool($schoolB, $yearId);
        $sectionA = $this->createSectionForClass((int) $classA->id);
        $sectionB = $this->createSectionForClass((int) $classB->id);
        $studentA = $this->createStudentForSchool($schoolA);
        $studentB = $this->createStudentForSchool($schoolB);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $enrA = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insertGetId([
            'student_id' => $studentA->id,
            'academic_year_id' => $yearId,
            'school_id' => $schoolA,
            'class_id' => $classA->id,
            'section_id' => $sectionA->id,
            'enrollment_number' => 'ENR-A-1',
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $enrB = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insertGetId([
            'student_id' => $studentB->id,
            'academic_year_id' => $yearId,
            'school_id' => $schoolB,
            'class_id' => $classB->id,
            'section_id' => $sectionB->id,
            'enrollment_number' => 'ENR-B-1',
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $idsA = collect(DB::select('SELECT id FROM enrollment.enrollments'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$enrA], $idsA);
        $this->assertNotContains($enrB, $idsA);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $idsB = collect(DB::select('SELECT id FROM enrollment.enrollments'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$enrB], $idsB);

        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM enrollment.enrollments')->c);

        DB::statement("SELECT set_config('app.current_school_id', '999999', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM enrollment.enrollments')->c);

        PostgreSqlRlsActor::reset();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $this->expectException(QueryException::class);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $enrA)->delete();
    }
}
