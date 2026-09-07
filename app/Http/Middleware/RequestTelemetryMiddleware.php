<?php

namespace App\Http\Middleware;

use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use App\Observability\RequestTelemetryContext;
use App\Observability\WorkloadResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestTelemetryMiddleware
{
    public function __construct(
        private readonly HttpRequestTelemetryMonitor $telemetryMonitor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sis.observability.http_enabled', true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        RequestTelemetryContext::begin($request, WorkloadResolver::forRoute($routeName));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('sis.observability.http_enabled', true) || ! RequestTelemetryContext::active()) {
            return;
        }

        $sample = RequestTelemetryContext::finish($response);
        if ($sample !== null) {
            $this->telemetryMonitor->record($sample);
        }

        RequestTelemetryContext::reset();
    }
}
