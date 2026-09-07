<?php

namespace App\Optimization\Commands;

use App\Optimization\OptimizationEngine;
use Illuminate\Console\Command;

class OptimizationRunCommand extends Command
{
    protected $signature = 'optimization:run';

    protected $description = 'Level 2 — apply ONE low-risk optimization with gates (requires OPTIMIZATION_MODE=autonomous)';

    public function handle(OptimizationEngine $engine): int
    {
        $this->warn('Direct optimization:run is disabled — use incident-driven self-healing cycle.');
        $this->line('Run: php artisan optimization:worker or wait for scheduled RunSelfHealingCycleJob.');

        $result = $engine->runAutonomous();

        $this->warn($result['reason'] ?? 'No optimization executed');

        return self::SUCCESS;
    }
}
