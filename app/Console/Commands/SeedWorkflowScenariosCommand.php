<?php

namespace App\Console\Commands;

use App\Database\SchemaHelper;
use Database\Seeders\WorkflowScenarioSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dev-only: wipe admission → student → enrollment → curriculum data and seed the
 * scenario set (230 applicants, 200 students) through the real handlers.
 *
 * --truncate really deletes rows (TRUNCATE … CASCADE bypasses the row-level
 * hard-delete triggers). Take a pg_dump first. Refused in production.
 *
 * php artisan sis:seed-workflow-scenarios --truncate
 */
class SeedWorkflowScenariosCommand extends Command
{
    protected $signature = 'sis:seed-workflow-scenarios
                            {--truncate : Delete existing workflow data first (dev only, irreversible)}';

    protected $description = 'Seed admission → student → enrollment → curriculum scenarios (200 students)';

    /** Roots; CASCADE also clears every table referencing them (grades, results, fees, …). */
    private const TRUNCATE_ROOTS = [
        ['admission', 'application_documents'],
        ['admission', 'applications'],
        ['admission', 'application_periods'],
        ['students', 'students'],
        ['enrollment', 'enrollments'],
        ['enrollment', 'enrollment_subjects'],
        ['curriculum', 'curriculum_subjects'],
        ['curriculum', 'curricula'],
        ['audit', 'outbox_messages'],
        ['audit', 'idempotency_keys'],
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Refused: workflow scenario seeding is for development databases only.');

            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            if (! SchemaHelper::isPostgreSql()) {
                $this->components->error('--truncate requires PostgreSQL.');

                return self::FAILURE;
            }
            $tables = implode(', ', array_map(
                static fn (array $t): string => SchemaHelper::qualified($t[0], $t[1]),
                self::TRUNCATE_ROOTS,
            ));
            $this->components->warn('Truncating workflow data (CASCADE) on '.DB::getDatabaseName().' ...');
            DB::statement("TRUNCATE {$tables} RESTART IDENTITY CASCADE");
        }

        $seeder = app(WorkflowScenarioSeeder::class);
        $seeder->setCommand($this);
        $seeder->setContainer(app());
        $seeder->__invoke();

        $this->table(['Metric', 'Count'], collect($seeder->summary)
            ->map(static fn (int $value, string $key): array => [$key, (string) $value])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
