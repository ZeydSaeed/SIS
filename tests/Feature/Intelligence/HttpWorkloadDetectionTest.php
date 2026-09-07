<?php

namespace Tests\Feature\Intelligence;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Detection;
use App\Intelligence\Models\Recommendation;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HttpWorkloadDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        HttpRequestTelemetryMonitor::resetSamples();
    }

    #[Test]
    public function performance_cycle_creates_detection_when_http_budget_exceeded(): void
    {
        $monitor = app(HttpRequestTelemetryMonitor::class);

        foreach (range(1, 20) as $index) {
            $monitor->record([
                'workload' => 'student_search',
                'route' => 'api.students.search',
                'method' => 'GET',
                'path' => '/api/v1/students/search',
                'duration_ms' => $index <= 18 ? 180.0 : 350.0,
                'db_queries' => 3,
                'status_code' => 200,
            ]);
        }

        $result = app(DatabaseGuardian::class)->runPerformanceCycle();

        $this->assertGreaterThanOrEqual(1, $result['http_workload_snapshots']);
        $this->assertGreaterThanOrEqual(1, $result['detections']);

        $this->assertDatabaseHas((new Detection)->getTable(), [
            'rule_id' => 'http_budget_exceeded',
            'status' => 'open',
        ]);

        $detection = Detection::query()
            ->where('rule_id', 'http_budget_exceeded')
            ->first();

        $this->assertNotNull($detection);
        $this->assertSame('student_search', $detection->evidence['workload'] ?? null);
        $this->assertTrue($detection->evidence['budget_exceeded'] ?? false);
    }

    #[Test]
    public function performance_cycle_creates_expert_recommendation_for_budget_breach(): void
    {
        $monitor = app(HttpRequestTelemetryMonitor::class);

        foreach (range(1, 10) as $index) {
            $monitor->record([
                'workload' => 'student_search',
                'route' => 'api.students.search',
                'method' => 'GET',
                'path' => '/api/v1/students/search',
                'duration_ms' => $index <= 8 ? 150.0 : 400.0,
                'db_queries' => 2,
                'status_code' => 200,
            ]);
        }

        app(DatabaseGuardian::class)->runPerformanceCycle();

        $this->assertDatabaseHas((new Recommendation)->getTable(), [
            'rule_id' => 'APP-001',
            'action_type' => 'investigate',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function performance_cycle_skips_detection_when_budget_not_exceeded(): void
    {
        $monitor = app(HttpRequestTelemetryMonitor::class);

        foreach (range(1, 10) as $index) {
            $monitor->record([
                'workload' => 'student_search',
                'route' => 'api.students.index',
                'method' => 'GET',
                'path' => '/api/v1/students',
                'duration_ms' => 45.0 + $index,
                'db_queries' => 2,
                'status_code' => 200,
            ]);
        }

        $result = app(DatabaseGuardian::class)->runPerformanceCycle();

        $this->assertSame(1, $result['http_workload_snapshots']);
        $this->assertSame(0, $result['detections']);
        $this->assertDatabaseCount((new Detection)->getTable(), 0);
    }
}
