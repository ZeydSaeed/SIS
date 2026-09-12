<?php

namespace Tests\Feature\Observability;

use App\Intelligence\Models\MonitoringSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PrometheusMetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function metrics_endpoint_returns_prometheus_text_when_token_unset_in_testing(): void
    {
        config(['sis.observability.metrics.token' => '']);

        MonitoringSnapshot::query()->create([
            'snapshot_type' => 'http_workload',
            'metrics' => [
                'workload' => 'finance_oltp',
                'p95_ms' => 88.5,
                'sample_count' => 4,
                'budget_exceeded' => false,
            ],
            'captured_at' => now(),
        ]);

        $response = $this->get('/api/v1/metrics');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('# TYPE sis_up gauge', $body);
        $this->assertStringContainsString('sis_up 1', $body);
        $this->assertStringContainsString('sis_http_workload_p95_ms{workload="finance_oltp"}', $body);
        $this->assertStringContainsString('sis_http_workload_samples{workload="finance_oltp"} 4', $body);
        $this->assertStringContainsString('sis_http_workload_budget_exceeded{workload="finance_oltp"} 0', $body);
    }

    #[Test]
    public function metrics_endpoint_requires_bearer_token_when_configured(): void
    {
        config(['sis.observability.metrics.token' => 'secret-metrics-token']);

        $this->get('/api/v1/metrics')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer wrong')
            ->get('/api/v1/metrics')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer secret-metrics-token')
            ->get('/api/v1/metrics')
            ->assertOk()
            ->assertSee('sis_up', false);
    }

    #[Test]
    public function metrics_endpoint_returns_404_when_disabled(): void
    {
        config([
            'sis.observability.metrics.enabled' => false,
            'sis.observability.metrics.token' => '',
        ]);

        $this->get('/api/v1/metrics')->assertNotFound();
    }
}
