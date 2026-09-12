<?php

namespace Tests\Feature\Observability;

use App\Intelligence\Models\MonitoringSnapshot;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use App\Observability\WorkloadResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LifecycleHttpWorkloadTelemetryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lifecycle_workloads_attach_provisional_budgets_on_flush(): void
    {
        HttpRequestTelemetryMonitor::resetSamples();
        $monitor = app(HttpRequestTelemetryMonitor::class);

        foreach (['api.finance.payments.index', 'api.workflow.approval-requests.cancel', 'api.communication.messages.index'] as $route) {
            $monitor->record([
                'workload' => WorkloadResolver::forRoute($route),
                'route' => $route,
                'method' => 'GET',
                'path' => '/api/v1/'.str_replace('.', '/', substr($route, 4)),
                'duration_ms' => 42.0,
                'db_queries' => 3,
                'status_code' => 200,
            ]);
        }

        $snapshots = $monitor->flush();
        $workloads = $snapshots->map(
            fn (MonitoringSnapshot $snapshot): ?string => $snapshot->metrics['workload'] ?? null,
        )->all();

        $this->assertEqualsCanonicalizing(
            ['finance_oltp', 'workflow_oltp', 'communication_oltp'],
            $workloads,
        );

        $finance = $snapshots->first(
            fn (MonitoringSnapshot $snapshot): bool => ($snapshot->metrics['workload'] ?? null) === 'finance_oltp',
        );

        $this->assertNotNull($finance);
        $this->assertSame(300, $finance->metrics['performance_budget_p95_ms'] ?? null);
        $this->assertFalse($finance->metrics['budget_exceeded'] ?? true);
    }
}
