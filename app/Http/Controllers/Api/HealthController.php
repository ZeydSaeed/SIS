<?php

namespace App\Http\Controllers\Api;

use App\Application\Observability\Queries\GetHealthStatusHandler;
use App\Application\Observability\Queries\GetHealthStatusQuery;
use App\Http\Controllers\Controller;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function show(Request $request, GetHealthStatusHandler $handler): JsonResponse
    {
        $status = $handler->handle(new GetHealthStatusQuery(
            correlationId: $request->header('X-Correlation-ID') ?? CorrelationContext::id(),
        ));

        return response()->json($status->toArray());
    }
}
