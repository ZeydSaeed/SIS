<?php

namespace App\Providers;

use App\Optimization\Commands\OptimizationObserveCommand;
use App\Optimization\Commands\OptimizationRecommendCommand;
use App\Optimization\Commands\OptimizationRunCommand;
use Illuminate\Support\ServiceProvider;

class OptimizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! config('optimization.enabled', true)) {
            return;
        }

        $this->commands([
            OptimizationObserveCommand::class,
            OptimizationRecommendCommand::class,
            OptimizationRunCommand::class,
        ]);
    }
}
