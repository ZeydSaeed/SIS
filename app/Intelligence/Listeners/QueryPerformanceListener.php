<?php

namespace App\Intelligence\Listeners;

use App\Intelligence\Monitoring\QueryMonitor;
use App\Observability\RequestTelemetryContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

class QueryPerformanceListener
{
    public function __construct(
        private readonly QueryMonitor $queryMonitor,
    ) {}

    public function handle(QueryExecuted $event): void
    {
        if (! config('intelligence.enabled', true)) {
            return;
        }

        if ($event->time < ($this->slowQueryThresholdMs())) {
            return;
        }

        $sql = Str::lower(trim($event->sql));
        if (str_starts_with($sql, 'select') === false) {
            return;
        }

        $fingerprint = hash('sha256', preg_replace('/\s+/', ' ', $sql) ?? $sql);
        $this->queryMonitor->recordRuntimeSample(
            $fingerprint,
            (float) $event->time,
            RequestTelemetryContext::queryLabel(),
        );
    }

    private function slowQueryThresholdMs(): float
    {
        if (RequestTelemetryContext::active()) {
            return (float) config('sis.observability.slow_query_threshold_ms', 10);
        }

        return 50.0;
    }
}
