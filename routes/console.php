<?php

use App\Infrastructure\Jobs\ProcessOutboxJob;
use App\Intelligence\Jobs\RunGrowthOptimizationJob;
use App\Intelligence\Jobs\RunHealthMonitorJob;
use App\Intelligence\Jobs\RunPerformanceAnalysisJob;
use App\Intelligence\Jobs\RunSchemaGuardianJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('intelligence.enabled', true)) {
    Schedule::job(new RunHealthMonitorJob)->everyMinute()->name('intelligence:health');
    Schedule::job(new RunPerformanceAnalysisJob)->everyFiveMinutes()->name('intelligence:performance');
    Schedule::job(new RunGrowthOptimizationJob)->hourly()->name('intelligence:growth');
    Schedule::job(new RunSchemaGuardianJob)->daily()->name('intelligence:schema');
    Schedule::job(new ProcessOutboxJob)->everyMinute()->name('architecture:outbox');
    Schedule::command('queue:work database --stop-when-empty --max-time=55')
        ->everyMinute()
        ->name('intelligence:queue-worker')
        ->withoutOverlapping();
}
