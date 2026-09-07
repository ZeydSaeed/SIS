<?php

namespace App\Application\Observability\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Observability\Contracts\DatabaseHealthPort;
use App\Application\Observability\Contracts\HttpWorkloadReadRepositoryInterface;
use App\Application\Observability\DTOs\HealthStatusDTO;

final class GetHealthStatusHandler implements QueryHandler
{
    public function __construct(
        private readonly DatabaseHealthPort $databaseHealth,
        private readonly HttpWorkloadReadRepositoryInterface $httpWorkloads,
    ) {}

    public function handle(Query $query): HealthStatusDTO
    {
        assert($query instanceof GetHealthStatusQuery);

        $databaseAvailable = $this->databaseHealth->isAvailable();

        return new HealthStatusDTO(
            status: $databaseAvailable ? 'ok' : 'degraded',
            version: (string) config('sis.api.version'),
            environment: (string) config('app.env'),
            profile: (string) config('sis.environment_profile'),
            correlationId: $query->correlationId,
            databaseConnection: (string) config('database.default'),
            databaseStatus: $databaseAvailable ? 'ok' : 'unavailable',
            cacheStore: (string) config('cache.default'),
            queueConnection: (string) config('queue.default'),
            optimizationMode: (string) config('optimization.mode'),
            productionAutonomousBlocked: (bool) config('optimization.autonomous.block_production', true),
            httpTelemetryEnabled: (bool) config('sis.observability.http_enabled', true),
            latestHttpWorkload: $this->httpWorkloads->latestSummary(),
            timestamp: now()->toIso8601String(),
        );
    }
}
