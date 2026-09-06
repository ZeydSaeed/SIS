<?php

namespace App\Providers;

use App\Intelligence\Commands\IntelligenceApproveCommand;
use App\Intelligence\Commands\IntelligenceGuardianCommand;
use App\Intelligence\Listeners\PostMigrationGuardianListener;
use App\Intelligence\Listeners\QueryPerformanceListener;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! config('intelligence.enabled', true)) {
            return;
        }

        $this->commands([
            IntelligenceGuardianCommand::class,
            IntelligenceApproveCommand::class,
        ]);

        Event::listen(QueryExecuted::class, QueryPerformanceListener::class);
        Event::listen(MigrationsEnded::class, PostMigrationGuardianListener::class);
    }
}
