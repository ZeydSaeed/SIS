<?php

namespace Tests\Feature\Integration;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Detection;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\Recommendation;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use App\Observability\Validation\WorkloadBudgetValidator;
use App\Observability\Validation\WorkloadValidationRunner;
use App\Optimization\SelfHealing\TelemetryCollector;
use Database\Seeders\WorkloadValidationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase3ApplicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        HttpRequestTelemetryMonitor::resetSamples();
    }

    #[Test]
    public function phase3_full_pipeline_from_health_through_students_to_intelligence(): void
    {
        $correlationId = 'phase3-integration-'.uniqid();

        $this->withHeader('X-Correlation-ID', $correlationId)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('profile', 'testing')
            ->assertJsonPath('optimization.production_autonomous_blocked', true)
            ->assertJsonPath('observability.http_telemetry_enabled', true)
            ->assertHeader('X-Correlation-ID', $correlationId);

        $this->seed(WorkloadValidationSeeder::class);

        $this->withHeader('X-Correlation-ID', $correlationId)
            ->postJson('/api/v1/students', [
                'first_name' => 'Integration',
                'last_name' => 'Student',
                'gender' => 1,
                'birth_date' => '2010-04-01',
                'student_code' => 'STU-PHASE3-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.student_code', 'STU-PHASE3-001');

        $this->getJson('/api/v1/students/search?q=Integration')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $metrics = app(WorkloadValidationRunner::class)->runProfile('student_search');

        $this->assertNotNull($metrics);
        $this->assertSame('student_search', $metrics['workload']);
        $this->assertGreaterThanOrEqual(30, $metrics['sample_count']);

        $report = app(WorkloadBudgetValidator::class)->validate('student_search', $metrics);

        $this->assertTrue(
            $report->passed,
            'Workload budget failed: '.json_encode($report->checks, JSON_THROW_ON_ERROR),
        );

        $cycle = app(DatabaseGuardian::class)->runPerformanceCycle();

        $this->assertGreaterThanOrEqual(1, $cycle['http_workload_snapshots']);
        $this->assertSame(0, $cycle['detections']);
        $this->assertSame(0, $cycle['recommendations']);
        $this->assertDatabaseCount((new Detection)->getTable(), 0);
        $this->assertDatabaseCount((new Recommendation)->getTable(), 0);

        $this->assertDatabaseHas((new MonitoringSnapshot)->getTable(), [
            'snapshot_type' => 'http_workload',
        ]);

        $telemetry = app(TelemetryCollector::class)->collect();

        $this->assertSame('http', $telemetry['p95_latency_source']);
        $this->assertNotNull($telemetry['p95_latency_ms']);
        $this->assertGreaterThan(0, $telemetry['http_request_samples']);
    }

    #[Test]
    public function phase3_budget_breach_pipeline_creates_detection_and_recommendation(): void
    {
        $monitor = app(HttpRequestTelemetryMonitor::class);

        foreach (range(1, 12) as $index) {
            $monitor->record([
                'workload' => 'student_search',
                'route' => 'api.students.search',
                'method' => 'GET',
                'path' => '/api/v1/students/search',
                'duration_ms' => $index <= 9 ? 150.0 : 450.0,
                'db_queries' => 2,
                'status_code' => 200,
            ]);
        }

        $cycle = app(DatabaseGuardian::class)->runPerformanceCycle();

        $this->assertGreaterThanOrEqual(1, $cycle['http_workload_snapshots']);
        $this->assertGreaterThanOrEqual(1, $cycle['detections']);
        $this->assertGreaterThanOrEqual(1, $cycle['recommendations']);

        $this->assertDatabaseHas((new Detection)->getTable(), [
            'rule_id' => 'http_budget_exceeded',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas((new Recommendation)->getTable(), [
            'rule_id' => 'APP-001',
            'action_type' => 'investigate',
        ]);
    }

    #[Test]
    public function phase3_validate_workload_command_is_end_to_end_entry_point(): void
    {
        $this->seed(WorkloadValidationSeeder::class);

        $this->artisan('sis:validate-workload', [
            'workload' => 'student_search',
            '--performance-cycle' => true,
        ])->assertSuccessful();
    }
}
