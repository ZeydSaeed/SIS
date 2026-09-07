<?php

namespace App\Application\Observability\DTOs;

final readonly class HealthStatusDTO
{
    /**
     * @param  array<string, mixed>|null  $latestHttpWorkload
     */
    public function __construct(
        public string $status,
        public string $version,
        public string $environment,
        public string $profile,
        public string $correlationId,
        public string $databaseConnection,
        public string $databaseStatus,
        public string $cacheStore,
        public string $queueConnection,
        public string $optimizationMode,
        public bool $productionAutonomousBlocked,
        public bool $httpTelemetryEnabled,
        public ?array $latestHttpWorkload,
        public string $timestamp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'version' => $this->version,
            'environment' => $this->environment,
            'profile' => $this->profile,
            'correlation_id' => $this->correlationId,
            'services' => [
                'database' => [
                    'connection' => $this->databaseConnection,
                    'status' => $this->databaseStatus,
                ],
                'cache' => [
                    'store' => $this->cacheStore,
                ],
                'queue' => [
                    'connection' => $this->queueConnection,
                ],
            ],
            'optimization' => [
                'mode' => $this->optimizationMode,
                'production_autonomous_blocked' => $this->productionAutonomousBlocked,
            ],
            'observability' => [
                'http_telemetry_enabled' => $this->httpTelemetryEnabled,
                'latest_http_workload' => $this->latestHttpWorkload,
            ],
            'timestamp' => $this->timestamp,
        ];
    }
}
