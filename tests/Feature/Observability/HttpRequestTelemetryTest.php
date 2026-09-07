<?php

namespace Tests\Feature\Observability;

use App\Intelligence\Models\MonitoringSnapshot;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use App\Optimization\SelfHealing\TelemetryCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class HttpRequestTelemetryTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function student_api_requests_emit_http_workload_snapshots_after_flush(): void
    {
        $this->actingAsStudentManager();

        $this->postJson('/api/v1/students', [
            'first_name' => 'Telemetry',
            'last_name' => 'Student',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'student_code' => 'STU-TEL-001',
        ])->assertCreated();

        $this->getJson('/api/v1/students/search?q=Telemetry')->assertOk();

        $snapshots = app(HttpRequestTelemetryMonitor::class)->flush();

        $this->assertGreaterThanOrEqual(1, $snapshots->count());
        $this->assertDatabaseHas((new MonitoringSnapshot)->getTable(), [
            'snapshot_type' => 'http_workload',
        ]);

        $studentSearch = $snapshots->first(
            fn (MonitoringSnapshot $snapshot): bool => ($snapshot->metrics['workload'] ?? null) === 'student_search',
        );

        $this->assertNotNull($studentSearch);
        $this->assertGreaterThan(0, $studentSearch->metrics['sample_count'] ?? 0);
        $this->assertArrayHasKey('p95_ms', $studentSearch->metrics ?? []);
    }

    #[Test]
    public function telemetry_collector_prefers_http_latency_when_workload_snapshots_exist(): void
    {
        MonitoringSnapshot::query()->create([
            'snapshot_type' => 'http_workload',
            'metrics' => [
                'workload' => 'student_search',
                'sample_count' => 5,
                'p95_ms' => 85.5,
                'p99_ms' => 120.0,
                'mean_db_queries_per_request' => 2.4,
            ],
            'captured_at' => now(),
        ]);

        $telemetry = app(TelemetryCollector::class)->collect();

        $this->assertSame('http', $telemetry['p95_latency_source']);
        $this->assertSame(85.5, $telemetry['p95_latency_ms']);
        $this->assertSame(2.4, $telemetry['db_queries_per_request']);
        $this->assertSame(5, $telemetry['http_request_samples']);
    }
}
