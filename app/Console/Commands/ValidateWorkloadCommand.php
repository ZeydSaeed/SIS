<?php

namespace App\Console\Commands;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Observability\Validation\WorkloadBudgetValidator;
use App\Observability\Validation\WorkloadValidationRunner;
use Illuminate\Console\Command;

class ValidateWorkloadCommand extends Command
{
    protected $signature = 'sis:validate-workload
                            {workload=student_search : Workload profile to validate}
                            {--seed : Seed workload validation students before running}
                            {--students= : Override seed student count}
                            {--performance-cycle : Run intelligence performance cycle after validation}';

    protected $description = 'Validate an HTTP workload against PERFORMANCE-BUDGET targets using real request telemetry';

    public function handle(
        WorkloadValidationRunner $runner,
        WorkloadBudgetValidator $validator,
        DatabaseGuardian $guardian,
    ): int {
        $workload = (string) $this->argument('workload');

        if ($this->option('seed')) {
            $count = (int) ($this->option('students')
                ?: config("sis.workload_validation.profiles.{$workload}.seed_students", 50));

            config(['sis.workload_validation._seed_count' => $count]);
            $this->components->info("Seeding {$count} students for workload validation...");
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\WorkloadValidationSeeder']);
        }

        $this->components->info("Running {$workload} validation profile...");
        $metrics = $runner->runProfile($workload);

        if ($metrics === null) {
            $this->components->error('No telemetry metrics captured for workload.');

            return self::FAILURE;
        }

        $report = $validator->validate($workload, $metrics);

        $this->newLine();
        $this->components->twoColumnDetail('Workload', $report->workload);
        $this->components->twoColumnDetail('Budget version', $report->performanceBudgetVersion);
        $this->components->twoColumnDetail('Samples', (string) ($metrics['sample_count'] ?? 0));
        $this->components->twoColumnDetail('P95', ($metrics['p95_ms'] ?? 'n/a').' ms');
        $this->components->twoColumnDetail('P99', ($metrics['p99_ms'] ?? 'n/a').' ms');
        $this->components->twoColumnDetail('Mean DB queries', (string) ($metrics['mean_db_queries_per_request'] ?? 'n/a'));

        $this->newLine();

        foreach ($report->checks as $check) {
            $this->renderCheck($check);
        }

        if ($this->option('performance-cycle')) {
            $this->newLine();
            $this->components->info('Running intelligence performance cycle...');
            $cycle = $guardian->runPerformanceCycle();
            $this->components->twoColumnDetail('HTTP snapshots', (string) ($cycle['http_workload_snapshots'] ?? 0));
            $this->components->twoColumnDetail('Detections', (string) ($cycle['detections'] ?? 0));
            $this->components->twoColumnDetail('Recommendations', (string) ($cycle['recommendations'] ?? 0));
        }

        $this->newLine();

        if ($report->passed) {
            $this->components->info('Workload validation passed.');

            return self::SUCCESS;
        }

        $this->components->error('Workload validation failed — see checks above.');

        return self::FAILURE;
    }

    /**
     * @param  array{name: string, status: string, expected: mixed, actual: mixed, detail: string}  $check
     */
    private function renderCheck(array $check): void
    {
        $label = match ($check['status']) {
            'pass' => '<fg=green>PASS</>',
            'skip' => '<fg=yellow>SKIP</>',
            default => '<fg=red>FAIL</>',
        };

        $this->line(" {$label} {$check['name']} — {$check['detail']}");
    }
}
