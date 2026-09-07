<?php

namespace App\Optimization\Jobs;

use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Continuous self-healing cycle — runs every 5 minutes via scheduler.
 * Lightweight monitoring always; diagnosis/healing only when degraded.
 */
class RunSelfHealingCycleJob implements ShouldQueue
{
    use Queueable;

    public function handle(SelfHealingPerformanceEngine $engine): void
    {
        if (! config('optimization.enabled', true)) {
            return;
        }

        $engine->runCycle();
    }
}
