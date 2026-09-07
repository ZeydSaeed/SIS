<?php

namespace App\Optimization\Commands;

use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use Illuminate\Console\Command;

class OptimizationWorkerCommand extends Command
{
    protected $signature = 'optimization:worker {--once : Run a single cycle and exit}';

    protected $description = 'Run the self-healing performance worker loop';

    public function handle(SelfHealingPerformanceEngine $engine): int
    {
        if (! config('optimization.worker.enabled', true)) {
            $this->warn('Optimization worker is disabled.');

            return self::SUCCESS;
        }

        if ($this->option('once')) {
            $result = $engine->runCycle();
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $interval = (int) config('optimization.worker.interval_seconds', 300);
        $maxExecution = (int) config('optimization.worker.max_execution_seconds', 120);
        $started = time();

        $this->info("Self-healing worker started (interval={$interval}s)");

        while (time() - $started < $maxExecution) {
            try {
                $result = $engine->runCycle();
                $this->line('['.now()->toIso8601String().'] '.($result['outcome'] ?? 'cycle'));
            } catch (\Throwable $e) {
                $this->error('Cycle error (contained): '.$e->getMessage());
            }

            sleep($interval);
        }

        $this->info('Worker max execution time reached — exiting safely.');

        return self::SUCCESS;
    }
}
