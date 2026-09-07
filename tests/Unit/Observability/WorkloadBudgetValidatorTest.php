<?php

namespace Tests\Unit\Observability;

use App\Observability\Validation\WorkloadBudgetValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkloadBudgetValidatorTest extends TestCase
{
    #[Test]
    public function passes_when_all_student_search_metrics_are_within_budget(): void
    {
        $report = (new WorkloadBudgetValidator)->validate('student_search', [
            'workload' => 'student_search',
            'sample_count' => 30,
            'p95_ms' => 85.0,
            'p99_ms' => 120.0,
            'mean_db_queries_per_request' => 2.1,
            'budget_exceeded' => false,
            'error_rate_pct' => 0.0,
        ]);

        $this->assertTrue($report->passed);
        $this->assertSame('PB-2026.09', $report->performanceBudgetVersion);
    }

    #[Test]
    public function fails_when_p95_exceeds_budget(): void
    {
        $report = (new WorkloadBudgetValidator)->validate('student_search', [
            'sample_count' => 30,
            'p95_ms' => 285.0,
            'p99_ms' => 320.0,
            'mean_db_queries_per_request' => 2.0,
            'budget_exceeded' => true,
            'error_rate_pct' => 0.0,
        ]);

        $this->assertFalse($report->passed);

        $p95Check = collect($report->checks)->firstWhere('name', 'p95_latency');
        $this->assertSame('fail', $p95Check['status']);
    }

    #[Test]
    public function fails_when_db_query_budget_exceeded(): void
    {
        $report = (new WorkloadBudgetValidator)->validate('student_search', [
            'sample_count' => 20,
            'p95_ms' => 90.0,
            'p99_ms' => 110.0,
            'mean_db_queries_per_request' => 6.5,
            'budget_exceeded' => false,
            'error_rate_pct' => 0.0,
        ]);

        $this->assertFalse($report->passed);

        $queryCheck = collect($report->checks)->firstWhere('name', 'mean_db_queries_per_request');
        $this->assertSame('fail', $queryCheck['status']);
    }
}
