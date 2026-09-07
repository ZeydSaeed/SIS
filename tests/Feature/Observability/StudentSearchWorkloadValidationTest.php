<?php

namespace Tests\Feature\Observability;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Detection;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use App\Observability\Validation\WorkloadBudgetValidator;
use App\Observability\Validation\WorkloadValidationRunner;
use Database\Seeders\WorkloadValidationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentSearchWorkloadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        HttpRequestTelemetryMonitor::resetSamples();
    }

    #[Test]
    public function student_search_workload_meets_performance_budget_under_sustained_traffic(): void
    {
        $this->seed(WorkloadValidationSeeder::class);

        $metrics = app(WorkloadValidationRunner::class)->runProfile('student_search');

        $this->assertNotNull($metrics);
        $this->assertSame('student_search', $metrics['workload']);
        $this->assertGreaterThanOrEqual(30, $metrics['sample_count']);

        $report = app(WorkloadBudgetValidator::class)->validate('student_search', $metrics);

        $this->assertTrue(
            $report->passed,
            'Budget validation failed: '.json_encode($report->checks, JSON_THROW_ON_ERROR),
        );
        $this->assertFalse($metrics['budget_exceeded'] ?? true);
    }

    #[Test]
    public function sustained_student_search_traffic_produces_no_intelligence_detections_when_within_budget(): void
    {
        $this->seed(WorkloadValidationSeeder::class);

        app(WorkloadValidationRunner::class)->runProfile('student_search');

        $cycle = app(DatabaseGuardian::class)->runPerformanceCycle();

        $this->assertGreaterThanOrEqual(1, $cycle['http_workload_snapshots']);
        $this->assertSame(0, $cycle['detections']);
        $this->assertDatabaseCount((new Detection)->getTable(), 0);
    }

    #[Test]
    public function validate_workload_command_passes_for_student_search_profile(): void
    {
        $this->seed(WorkloadValidationSeeder::class);

        $this->artisan('sis:validate-workload', [
            'workload' => 'student_search',
            '--performance-cycle' => true,
        ])->assertSuccessful();
    }
}
