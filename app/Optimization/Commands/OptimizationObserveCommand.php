<?php

namespace App\Optimization\Commands;

use App\Optimization\OptimizationEngine;
use Illuminate\Console\Command;

class OptimizationObserveCommand extends Command
{
    protected $signature = 'optimization:observe';

    protected $description = 'Level 0 — collect metrics and baseline without modifications';

    public function handle(OptimizationEngine $engine): int
    {
        $result = $engine->observe();

        $this->info('Optimization observe cycle complete.');
        $this->line('Baseline: '.($result['baseline_code'] ?? 'n/a'));
        $this->line('Mode: '.($result['mode'] ?? 'observe'));

        return self::SUCCESS;
    }
}
