<?php

namespace Tests\Feature\Security\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

class AdmissionRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function missing_school_context_is_fail_closed(): void
    {
        [$schoolA, $yearId] = $this->seedSchoolAndYear();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $this->insertPeriod($schoolA, $yearId, 'Period Hidden');

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', '', true)");

        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM admission.application_periods')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM admission.applications')->c);
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM admission.application_documents')->c);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function school_context_isolates_admission_rows_across_schools(): void
    {
        [$schoolA, $schoolB, $yearId, $gradeId] = $this->seedTwoSchoolsAndReferences();

        // Seed as table owner/superuser (setup), then assert as non-superuser RLS actor.
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $periodA = $this->insertPeriod($schoolA, $yearId, 'Period A');
        $appA = $this->insertApplication($periodA, $gradeId, 'APP-A-1');
        $this->insertDocument($appA, 'doc-a');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $periodB = $this->insertPeriod($schoolB, $yearId, 'Period B');
        $appB = $this->insertApplication($periodB, $gradeId, 'APP-B-1');
        $this->insertDocument($appB, 'doc-b');

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $periodIds = collect(DB::select('SELECT id FROM admission.application_periods'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $applicationIds = collect(DB::select('SELECT id FROM admission.applications'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $documentCount = (int) DB::selectOne('SELECT COUNT(*) AS c FROM admission.application_documents')->c;

        $this->assertSame([$periodA], $periodIds);
        $this->assertSame([$appA], $applicationIds);
        $this->assertSame(1, $documentCount);
        $this->assertNotContains($periodB, $periodIds);
        $this->assertNotContains($appB, $applicationIds);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $periodIdsB = collect(DB::select('SELECT id FROM admission.application_periods'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$periodB], $periodIdsB);

        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS c FROM admission.application_periods')->c);

        PostgreSqlRlsActor::reset();
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedSchoolAndYear(): array
    {
        [$schoolA, $schoolB, $yearId] = array_slice($this->seedTwoSchoolsAndReferences(), 0, 3);

        return [$schoolA, $yearId];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function seedTwoSchoolsAndReferences(): array
    {
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MOE-T',
            'name' => 'Test Ministry',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DIR-T',
            'name' => 'Test Dir',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-A',
            'name' => 'School A',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolB = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-B',
            'name' => 'School B',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-T',
            'name' => 'Test Year',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G10T',
            'name' => 'Grade 10',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);

        return [$schoolA, $schoolB, $yearId, $gradeId];
    }

    private function insertPeriod(int $schoolId, int $yearId, string $name): int
    {
        return (int) DB::table('admission.application_periods')->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'name' => $name,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'max_applications' => 100,
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    private function insertApplication(int $periodId, int $gradeId, string $number): int
    {
        return (int) DB::table('admission.applications')->insertGetId([
            'application_period_id' => $periodId,
            'application_number' => $number,
            'first_name' => 'Ali',
            'last_name' => 'Test',
            'national_id' => null,
            'birth_date' => '2010-01-01',
            'gender' => 1,
            'grade_level_id' => $gradeId,
            'specialization_id' => null,
            'status' => 2,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertDocument(int $applicationId, string $key): int
    {
        return (int) DB::table('admission.application_documents')->insertGetId([
            'application_id' => $applicationId,
            'document_type' => 1,
            'storage_key' => $key,
            'file_name' => $key.'.pdf',
            'file_hash' => str_repeat('a', 64),
            'created_at' => now(),
        ]);
    }
}
