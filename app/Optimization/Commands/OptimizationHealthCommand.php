<?php

namespace App\Optimization\Commands;

use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use Illuminate\Console\Command;

class OptimizationHealthCommand extends Command
{
    protected $signature = 'optimization:health';

    protected $description = 'Show current health score, telemetry, and detected anomalies';

    public function handle(SelfHealingPerformanceEngine $engine): int
    {
        $health = $engine->health();

        $this->info('Health Score: '.($health['health']['overall_score'] ?? 'N/A'));
        $this->line('Status: '.($health['health']['status'] ?? 'unknown'));
        $this->line('Anomalies: '.($health['anomaly_count'] ?? 0));

        foreach ($health['health']['dimensions'] ?? [] as $metric => $dim) {
            $this->line("  {$metric}: {$dim['status']} ({$dim['current']} vs baseline {$dim['baseline']})");
        }

        return self::SUCCESS;
    }
}
