<?php

namespace App\Providers;

use App\Observability\Listeners\CountDatabaseQueriesForRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! config('sis.observability.http_enabled', true)) {
            return;
        }

        Event::listen(QueryExecuted::class, CountDatabaseQueriesForRequest::class);
    }
}
