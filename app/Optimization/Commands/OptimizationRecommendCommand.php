<?php

namespace App\Optimization\Commands;

use App\Optimization\OptimizationEngine;
use Illuminate\Console\Command;

class OptimizationRecommendCommand extends Command
{
    protected $signature = 'optimization:recommend {--analyze : Alias for recommend mode}';

    protected $description = 'Level 1 — detect bottlenecks, score candidates, generate reports';

    public function handle(OptimizationEngine $engine): int
    {
        $result = $engine->recommend();

        $this->info('Optimization recommend cycle complete.');
        $this->line('Bottlenecks detected: '.($result['count'] ?? 0));
        $this->line('Report: .cursor/architecture/optimization/BOTTLENECK-REPORT.md');

        return self::SUCCESS;
    }
}
