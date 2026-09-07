<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    #[Test]
    public function health_endpoint_returns_ok_with_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'version',
                'environment',
                'profile',
                'correlation_id',
                'services' => [
                    'database' => ['connection', 'status'],
                    'cache' => ['store'],
                    'queue' => ['connection'],
                ],
                'optimization' => ['mode', 'production_autonomous_blocked'],
                'observability' => ['http_telemetry_enabled', 'latest_http_workload'],
                'timestamp',
            ])
            ->assertJsonPath('environment', 'testing')
            ->assertJsonPath('profile', 'testing')
            ->assertJsonPath('optimization.mode', 'observe')
            ->assertJsonPath('optimization.production_autonomous_blocked', true);

        $this->assertNotEmpty($response->headers->get('X-Correlation-ID'));
    }

    #[Test]
    public function health_endpoint_echoes_inbound_correlation_id(): void
    {
        $correlationId = 'REQ-PHASE-3-1-TEST';

        $response = $this->withHeader('X-Correlation-ID', $correlationId)
            ->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('correlation_id', $correlationId)
            ->assertHeader('X-Correlation-ID', $correlationId);
    }
}
