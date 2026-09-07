<?php

namespace App\Providers;

use App\Optimization\Commands\OptimizationBaselineCommand;
use App\Optimization\Commands\OptimizationHealthCommand;
use App\Optimization\Commands\OptimizationHistoryCommand;
use App\Optimization\Commands\OptimizationObserveCommand;
use App\Optimization\Commands\OptimizationRecommendCommand;
use App\Optimization\Commands\OptimizationRollbackCommand;
use App\Optimization\Commands\OptimizationRunCommand;
use App\Optimization\Commands\OptimizationStatusCommand;
use App\Optimization\Commands\OptimizationWorkerCommand;
use App\Optimization\Execution\AnalyzeTableOperation;
use App\Optimization\Execution\OptimizationOperationRegistry;
use Illuminate\Support\ServiceProvider;

class OptimizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OptimizationOperationRegistry::class, function ($app) {
            $registry = new OptimizationOperationRegistry;
            $registry->register($app->make(AnalyzeTableOperation::class));

            return $registry;
        });
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
            OptimizationStatusCommand::class,
            OptimizationHealthCommand::class,
            OptimizationBaselineCommand::class,
            OptimizationHistoryCommand::class,
            OptimizationRollbackCommand::class,
            OptimizationWorkerCommand::class,
        ]);
    }
}
