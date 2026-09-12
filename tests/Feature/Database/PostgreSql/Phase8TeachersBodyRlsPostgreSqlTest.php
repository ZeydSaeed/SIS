<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

final class Phase8TeachersBodyRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function teachers_and_qualifications_have_force_rls(): void
    {
        foreach (['teachers', 'teacher_qualifications'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'teachers' AND c.relname = ?
            ", [$table]);

            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls, "{$table} RLS");
            $this->assertTrue((bool) $row->force_rls, "{$table} FORCE RLS");
        }
    }

    #[Test]
    public function body_rls_hides_teacher_and_qualification_from_other_school(): void
    {
        $schoolA = $this->createSchool('SCH-8-RLS-A', 'Teachers RLS A');
        $schoolB = $this->createSchool('SCH-8-RLS-B', 'Teachers RLS B');
        $yearId = $this->createAcademicYear('AY-8-RLS');
        $this->actingAsTeachersManagerForSchool($schoolA);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-RLS-1',
            'first_name' => 'Rls',
            'last_name' => 'Teacher',
        ], ['X-Idempotency-Key' => '8-rls-reg'])->json('data.teacher_id');

        $qualificationId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => 1,
            'title' => 'Cert',
        ], ['X-Idempotency-Key' => '8-rls-qual'])->json('data.qualification_id');

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $this->assertSame(
            1,
            (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('id', $teacherId)->count(),
        );
        $this->assertSame(
            1,
            (int) DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))->where('id', $qualificationId)->count(),
        );

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $this->assertSame(
            0,
            (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('id', $teacherId)->count(),
        );
        $this->assertSame(
            0,
            (int) DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))->where('id', $qualificationId)->count(),
        );

        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(
            0,
            (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('id', $teacherId)->count(),
        );

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function register_and_list_still_work_under_http_school_context(): void
    {
        $schoolId = $this->createSchool('SCH-8-RLS-H', 'Teachers RLS HTTP');
        $yearId = $this->createAcademicYear('AY-8-RLS-H');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-RLS-H',
            'first_name' => 'Http',
            'last_name' => 'Ok',
        ], ['X-Idempotency-Key' => '8-rls-http'])->json('data.teacher_id');

        $this->getJson('/api/v1/teachers?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $teacherId);
    }
}
