<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

class AdmissionCheckConstraintPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function application_period_rejects_end_before_start(): void
    {
        [$schoolId, $yearId] = $this->seedSchoolAndYear();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('admission.application_periods')->insert([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => 'Bad Period',
            'start_date' => now()->addDay(),
            'end_date' => now()->subDay(),
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function application_rejects_invalid_status_and_gender(): void
    {
        [$schoolId, $yearId, $gradeId, $periodId] = $this->seedPeriod();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        try {
            DB::table('admission.applications')->insert([
                'application_period_id' => $periodId,
                'application_number' => 'BAD-STATUS',
                'first_name' => 'A',
                'last_name' => 'B',
                'birth_date' => '2010-01-01',
                'gender' => 1,
                'grade_level_id' => $gradeId,
                'status' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected status CHECK to reject status=99');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertTrue(true);
        }

        try {
            DB::table('admission.applications')->insert([
                'application_period_id' => $periodId,
                'application_number' => 'BAD-GENDER',
                'first_name' => 'A',
                'last_name' => 'B',
                'birth_date' => '2010-01-01',
                'gender' => 9,
                'grade_level_id' => $gradeId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected gender CHECK to reject gender=9');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function valid_application_insert_succeeds(): void
    {
        [$schoolId, $yearId, $gradeId, $periodId] = $this->seedPeriod();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $id = DB::table('admission.applications')->insertGetId([
            'application_period_id' => $periodId,
            'application_number' => 'OK-1',
            'first_name' => 'Sara',
            'last_name' => 'Test',
            'birth_date' => '2011-02-02',
            'gender' => 2,
            'grade_level_id' => $gradeId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $id);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedSchoolAndYear(): array
    {
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MOE-C',
            'name' => 'Ministry C',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DIR-C',
            'name' => 'Dir C',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolId = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-C',
            'name' => 'School C',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-C',
            'name' => 'Year C',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$schoolId, $yearId];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedPeriod(): array
    {
        [$schoolId, $yearId] = $this->seedSchoolAndYear();
        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G11C',
            'name' => 'Grade 11',
            'level_order' => 11,
            'education_stage' => 1,
            'status' => 1,
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $periodId = (int) DB::table('admission.application_periods')->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => 'OK Period',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'status' => 1,
            'created_at' => now(),
        ]);

        return [$schoolId, $yearId, $gradeId, $periodId];
    }
}
