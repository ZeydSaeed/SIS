<?php

namespace App\Intelligence\Commands;

use App\Intelligence\Guardian\DatabaseGuardian;
use Illuminate\Console\Command;

class IntelligenceGuardianCommand extends Command
{
    protected $signature = 'intelligence:guardian {cycle=all : health|performance|growth|schema|all}';

    protected $description = 'Run SIS Database Guardian intelligence cycles';

    public function handle(DatabaseGuardian $guardian): int
    {
        if (! config('intelligence.enabled', true)) {
            $this->warn('Intelligence layer is disabled (INTELLIGENCE_ENABLED=false).');

            return self::SUCCESS;
        }

        $cycle = $this->argument('cycle');

        $results = match ($cycle) {
            'health' => ['health' => $guardian->runHealthCycle()],
            'performance' => ['performance' => $guardian->runPerformanceCycle()],
            'growth' => ['growth' => $guardian->runGrowthAndOptimizationCycle()],
            'schema' => ['schema' => $guardian->runSchemaValidation()->values()->all()],
            default => [
                'health' => $guardian->runHealthCycle(),
                'performance' => $guardian->runPerformanceCycle(),
                'growth' => $guardian->runGrowthAndOptimizationCycle(),
                'schema' => $guardian->runSchemaValidation()->values()->all(),
            ],
        };

        $this->info('Database Guardian cycle complete.');
        $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
