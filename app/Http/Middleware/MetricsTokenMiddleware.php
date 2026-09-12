<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MetricsTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('sis.observability.metrics.enabled', true)) {
            abort(404);
        }

        $configured = (string) config('sis.observability.metrics.token', '');
        $env = (string) config('app.env');
        $openEnvs = config('sis.observability.metrics.allow_unauthenticated_in', ['local', 'testing']);

        if ($configured === '') {
            if (! in_array($env, $openEnvs, true)) {
                abort(401, 'Metrics token not configured.');
            }

            return $next($request);
        }

        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            abort(401, 'Metrics bearer token required.');
        }

        if (! hash_equals($configured, trim($matches[1]))) {
            abort(401, 'Invalid metrics token.');
        }

        return $next($request);
    }
}
