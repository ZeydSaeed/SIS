<?php

namespace App\Optimization\Commands;

use App\Optimization\SelfHealing\CircuitBreaker;
use App\Optimization\SelfHealing\RollbackCoordinator;
use Illuminate\Console\Command;

class OptimizationRollbackCommand extends Command
{
    protected $signature = 'optimization:rollback {historyId : Optimization history record ID} {--reset-safe-mode : Exit circuit-breaker safe mode}';

    protected $description = 'Rollback an optimization by history ID';

    public function handle(RollbackCoordinator $rollback, CircuitBreaker $circuitBreaker): int
    {
        if ($this->option('reset-safe-mode')) {
            $circuitBreaker->reset();
            $this->info('Safe mode cleared. Autonomous mode restored per OPTIMIZATION_MODE config.');

            return self::SUCCESS;
        }

        $historyId = $this->argument('historyId');

        if ($rollback->rollbackByHistoryId($historyId)) {
            $this->info("Rollback initiated for {$historyId}");

            return self::SUCCESS;
        }

        $this->error("History record not found: {$historyId}");

        return self::FAILURE;
    }
}
