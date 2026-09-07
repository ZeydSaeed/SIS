<?php

namespace App\Observability;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequestTelemetryContext
{
    private static bool $active = false;

    private static ?float $startedAt = null;

    private static int $queryCount = 0;

    private static ?string $routeName = null;

    private static ?string $workload = null;

    private static ?string $method = null;

    private static ?string $path = null;

    public static function begin(Request $request, ?string $workload): void
    {
        self::$active = true;
        self::$startedAt = microtime(true);
        self::$queryCount = 0;
        self::$routeName = $request->route()?->getName();
        self::$workload = $workload;
        self::$method = $request->method();
        self::$path = $request->path();
    }

    public static function incrementQueryCount(): void
    {
        if (! self::$active) {
            return;
        }

        self::$queryCount++;
    }

    public static function active(): bool
    {
        return self::$active;
    }

    public static function queryLabel(): ?string
    {
        if (self::$routeName !== null) {
            return self::$routeName;
        }

        return self::$path;
    }

    /**
     * @return array{
     *     workload: string,
     *     route: ?string,
     *     method: ?string,
     *     path: ?string,
     *     duration_ms: float,
     *     db_queries: int,
     *     status_code: int
     * }|null
     */
    public static function finish(Response $response): ?array
    {
        if (! self::$active || self::$startedAt === null) {
            return null;
        }

        $durationMs = round((microtime(true) - self::$startedAt) * 1000, 2);

        return [
            'workload' => self::$workload ?? 'unknown',
            'route' => self::$routeName,
            'method' => self::$method,
            'path' => self::$path,
            'duration_ms' => $durationMs,
            'db_queries' => self::$queryCount,
            'status_code' => $response->getStatusCode(),
        ];
    }

    public static function reset(): void
    {
        self::$active = false;
        self::$startedAt = null;
        self::$queryCount = 0;
        self::$routeName = null;
        self::$workload = null;
        self::$method = null;
        self::$path = null;
    }
}
