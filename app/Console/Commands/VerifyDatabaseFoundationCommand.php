<?php

namespace App\Console\Commands;

use App\Database\DatabaseFoundationVerifier;
use Illuminate\Console\Command;

class VerifyDatabaseFoundationCommand extends Command
{
    protected $signature = 'sis:verify-database
                            {--seed : Run SisFoundationSeeder before verification}
                            {--no-seed-check : Skip foundation seed presence check}';

    protected $description = 'Verify PostgreSQL migrations, schemas, and foundation seed data';

    public function handle(DatabaseFoundationVerifier $verifier): int
    {
        if ($this->option('seed')) {
            $this->components->info('Seeding foundation reference data...');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\SisFoundationSeeder']);
        }

        $report = $verifier->verify(requireFoundationSeed: ! $this->option('no-seed-check'));

        $this->newLine();
        $this->components->twoColumnDetail('Connection', $report['connection'].' ('.$report['driver'].')');

        if ($report['summary']['table_count'] !== null) {
            $this->components->twoColumnDetail('Business tables', (string) $report['summary']['table_count']);
        }

        $this->newLine();

        foreach ($report['checks'] as $check) {
            $this->renderCheck($check);
        }

        $this->newLine();
        $this->components->twoColumnDetail('Checks passed', (string) $report['summary']['passed'].' / '.$report['summary']['total_checks']);

        if ($report['ok']) {
            $this->components->info('Database foundation verification passed.');

            return self::SUCCESS;
        }

        $this->components->error('Database foundation verification failed.');

        return self::FAILURE;
    }

    /**
     * @param  array{name: string, status: string, detail: string}  $check
     */
    private function renderCheck(array $check): void
    {
        $label = match ($check['status']) {
            'pass' => '<fg=green>PASS</>',
            'warn' => '<fg=yellow>WARN</>',
            default => '<fg=red>FAIL</>',
        };

        $this->line(" {$label} {$check['name']} — {$check['detail']}");
    }
}
