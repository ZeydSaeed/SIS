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
        $result = $engine->runAutonomous();

        if (! ($result['executed'] ?? false)) {
            $this->warn($result['reason'] ?? 'No optimization executed');

            return self::SUCCESS;
        }

        $this->info('Optimization executed: '.($result['event_code'] ?? 'n/a'));
        $this->line('Decision: '.($result['decision'] ?? 'unknown'));
        $this->line('History: '.($result['history_id'] ?? 'n/a'));

        return self::SUCCESS;
    }
}
