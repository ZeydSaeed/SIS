<?php

namespace App\Observability\Listeners;

use App\Observability\RequestTelemetryContext;
use Illuminate\Database\Events\QueryExecuted;

final class CountDatabaseQueriesForRequest
{
    public function handle(QueryExecuted $event): void
    {
        if (! config('sis.observability.http_enabled', true)) {
            return;
        }

        RequestTelemetryContext::incrementQueryCount();
    }
}
