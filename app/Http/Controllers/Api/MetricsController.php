<?php

namespace App\Http\Controllers\Api;

use App\Application\Observability\Queries\GetPrometheusMetricsHandler;
use App\Application\Observability\Queries\GetPrometheusMetricsQuery;
use App\Http\Controllers\Controller;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function show(Request $request, GetPrometheusMetricsHandler $handler): Response
    {
        $metrics = $handler->handle(new GetPrometheusMetricsQuery(
            correlationId: $request->header('X-Correlation-ID') ?? CorrelationContext::id(),
        ));

        return response($metrics->body, 200, [
            'Content-Type' => $metrics->contentType,
            'Cache-Control' => 'no-store',
        ]);
    }
}
