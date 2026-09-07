<?php

namespace App\Optimization\Jobs;

use App\Optimization\OptimizationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunOptimizationObserveJob implements ShouldQueue
{
    use Queueable;

    public function handle(OptimizationEngine $engine): void
    {
        if (! config('optimization.enabled', true)) {
            return;
        }

        if (config('optimization.mode') !== 'observe') {
            return;
        }

        $engine->observe();
    }
}
