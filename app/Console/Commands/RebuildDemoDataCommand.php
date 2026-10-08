<?php

namespace App\Console\Commands;

use App\Database\SchemaHelper;
use Database\Seeders\DemoProfileCompletionSeeder;
use Database\Seeders\FoundationAcademicSeeder;
use Database\Seeders\RoomsAndWorkshopsSeeder;
use Database\Seeders\Support\FoundationReference;
use Database\Seeders\TeachersScenarioSeeder;
use Database\Seeders\TimetableRoomsSeeder;
use Database\Seeders\TimetableScenarioSeeder;
use Database\Seeders\WorkflowScenarioSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dev-only: rebuilds the demo data of admission · students · enrollment · teachers · curriculum · timetable
 * from a clean slate, through the real handlers, with one catalogue (one spelling per branch / department /
 * class / section / subject): 200 students, 50 teachers, the same curriculum everywhere.
 *
 * Truncates (CASCADE — every table that references the roots goes too). Users, roles, schools,
 * directorates and academic years are kept. Refused in production.
 *
 * php artisan sis:rebuild-demo-data
 */
class RebuildDemoDataCommand extends Command
{
    protected $signature = 'sis:rebuild-demo-data
                            {--skip-timetable : Do not build the demo timetable}';

    protected $description = 'Wipe and rebuild the demo data (200 students, 50 teachers, one catalogue) through the real handlers';

    /** Roots; CASCADE also clears everything that references them (grades, exams, results, fees, …). */
    private const TRUNCATE_ROOTS = [
        ['admission', 'application_documents'],
        ['admission', 'applications'],
        ['admission', 'application_periods'],
        ['students', 'students'],
        ['enrollment', 'enrollments'],
        ['enrollment', 'enrollment_subjects'],
        ['enrollment', 'sections'],
        ['enrollment', 'classes'],
        ['curriculum', 'curriculum_subjects'],
        ['curriculum', 'curricula'],
        ['curriculum', 'subjects'],
        ['teachers', 'teachers'],
        ['teachers', 'teacher_schools'],
        ['teachers', 'teacher_subjects'],
        ['teachers', 'teaching_assignments'],
        ['timetable', 'schedules'],
        ['timetable', 'periods'],
        ['vocational', 'workshops'],
        ['organization', 'rooms'],
        ['vocational', 'specializations'],
        ['organization', 'departments'],
        ['organization', 'branches'],
        ['audit', 'outbox_messages'],
        ['audit', 'idempotency_keys'],
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Refused: demo data is rebuilt on development databases only.');

            return self::FAILURE;
        }
        if (! SchemaHelper::isPostgreSql()) {
            $this->components->error('Requires PostgreSQL.');

            return self::FAILURE;
        }

        $tables = implode(', ', array_map(
            static fn (array $t): string => SchemaHelper::qualified($t[0], $t[1]),
            self::TRUNCATE_ROOTS,
        ));
        $this->components->warn('Truncating demo data (CASCADE) on '.DB::getDatabaseName().' ...');
        DB::statement("TRUNCATE {$tables} RESTART IDENTITY CASCADE");

        $this->dropStrayGradeLevels();
        $this->seedClass(FoundationAcademicSeeder::class);

        $workflow = app(WorkflowScenarioSeeder::class);
        $this->runSeeder($workflow);
        $this->runSeeder(app(TeachersScenarioSeeder::class));
        $this->runSeeder(app(DemoProfileCompletionSeeder::class));
        $this->runSeeder(app(RoomsAndWorkshopsSeeder::class));
        if (! $this->option('skip-timetable')) {
            $this->runSeeder(app(TimetableScenarioSeeder::class));
            $this->runSeeder(app(TimetableRoomsSeeder::class));
        }

        $this->table(['Metric', 'Count'], collect($workflow->summary)
            ->map(static fn (int $value, string $key): array => [$key, (string) $value])
            ->values()
            ->all());
        $this->components->info('Done. Run `php artisan sis:audit-data-quality` to verify.');

        return self::SUCCESS;
    }

    /** Grade levels outside the class SSOT (الأول / الثاني / الثالث) — unreferenced after the wipe. */
    private function dropStrayGradeLevels(): void
    {
        DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->whereNotIn('code', array_column(FoundationReference::GRADE_LEVELS, 'code'))
            ->delete();
    }

    private function runSeeder(object $seeder): void
    {
        $seeder->setCommand($this);
        $seeder->setContainer(app());
        $seeder->__invoke();
    }

    /** @param  class-string  $class */
    private function seedClass(string $class): void
    {
        $this->runSeeder(app($class));
    }
}
