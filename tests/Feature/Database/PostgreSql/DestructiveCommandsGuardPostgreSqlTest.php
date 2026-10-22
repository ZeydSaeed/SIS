<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * The data-reset commands never touch a database the guard does not allow — whatever DB_DATABASE says. The live
 * database is never used to prove it: the isolated test database is declared protected / not allowed instead, and the
 * commands must refuse before anything is truncated.
 */
final class DestructiveCommandsGuardPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private function marker(): int
    {
        $schoolId = $this->createSchool('SCH-GUARD', 'Guard probe');
        $this->createAcademicYear('AY-GUARD');
        $this->createStudentForSchool($schoolId, ['full_name' => 'طالب الحماية']);

        return (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->count();
    }

    #[Test]
    public function rebuild_demo_data_refuses_a_protected_database(): void
    {
        $schools = $this->marker();
        config(['sis.database.protected_names' => [DB::getDatabaseName()]]);

        $this->artisan('sis:rebuild-demo-data')->expectsOutputToContain('Refused')->assertFailed();
        $this->assertSame($schools, (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->count());
    }

    #[Test]
    public function rebuild_demo_data_refuses_a_database_not_allowed_for_destructive_work(): void
    {
        $this->marker();
        config(['sis.database.protected_names' => ['sis'], 'sis.database.destructive_allowed_names' => ['some_other_db']]);
        $students = (int) DB::table(SchemaHelper::qualified('students', 'students'))->count();

        $this->artisan('sis:rebuild-demo-data')->expectsOutputToContain('Refused')->assertFailed();
        $this->assertSame($students, (int) DB::table(SchemaHelper::qualified('students', 'students'))->count());
    }

    #[Test]
    public function the_e2e_seed_refuses_any_database_not_named_sis_e2e(): void
    {
        $this->marker();
        config(['sis.database.destructive_allowed_names' => [DB::getDatabaseName()]]);
        if (str_starts_with(DB::getDatabaseName(), 'sis_e2e')) {
            // The suite itself runs on sis_e2e_unit: prove the name rule on the protected-name path instead.
            config(['sis.database.protected_names' => [DB::getDatabaseName()]]);
        }

        $this->artisan('sis:e2e-seed', ['--no-backup' => true])->expectsOutputToContain('Refused')->assertFailed();
    }
}
