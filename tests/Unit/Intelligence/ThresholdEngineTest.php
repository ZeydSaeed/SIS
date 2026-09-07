<?php

namespace Tests\Unit\Intelligence;

use App\Intelligence\Detection\ThresholdEngine;
use App\Intelligence\Support\ConditionEvaluator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThresholdEngineTest extends TestCase
{
    #[Test]
    public function evaluate_http_workload_detects_budget_exceeded(): void
    {
        $engine = new ThresholdEngine(new ConditionEvaluator);

        $matches = $engine->evaluateHttpWorkload([
            'workload' => 'student_search',
            'routes' => ['api.students.search'],
            'sample_count' => 20,
            'p95_ms' => 285.0,
            'performance_budget_p95_ms' => 200,
            'budget_exceeded' => true,
            'error_rate_pct' => 0.0,
            'mean_db_queries_per_request' => 3.2,
            'source' => 'http_request_telemetry',
        ]);

        $this->assertTrue($matches->contains(
            fn (array $match): bool => $match['rule_id'] === 'http_budget_exceeded',
        ));
    }

    #[Test]
    public function evaluate_http_workload_skips_budget_rule_when_within_budget(): void
    {
        $engine = new ThresholdEngine(new ConditionEvaluator);

        $matches = $engine->evaluateHttpWorkload([
            'workload' => 'student_search',
            'sample_count' => 20,
            'p95_ms' => 85.0,
            'performance_budget_p95_ms' => 200,
            'budget_exceeded' => false,
            'error_rate_pct' => 0.0,
            'mean_db_queries_per_request' => 2.0,
        ]);

        $this->assertFalse($matches->contains(
            fn (array $match): bool => $match['rule_id'] === 'http_budget_exceeded',
        ));
    }

    #[Test]
    public function evaluate_http_workload_detects_query_heavy_requests(): void
    {
        $engine = new ThresholdEngine(new ConditionEvaluator);

        $matches = $engine->evaluateHttpWorkload([
            'workload' => 'student_search',
            'sample_count' => 10,
            'p95_ms' => 120.0,
            'performance_budget_p95_ms' => 200,
            'budget_exceeded' => false,
            'error_rate_pct' => 0.0,
            'mean_db_queries_per_request' => 14.5,
        ]);

        $this->assertTrue($matches->contains(
            fn (array $match): bool => $match['rule_id'] === 'http_query_heavy',
        ));
    }

    #[Test]
    public function persist_detection_includes_workload_context_in_evidence(): void
    {
        $engine = new ThresholdEngine(new ConditionEvaluator);

        $matches = $engine->evaluateHttpWorkload([
            'workload' => 'student_search',
            'routes' => ['api.students.search'],
            'sample_count' => 5,
            'p95_ms' => 250.0,
            'performance_budget_p95_ms' => 200,
            'budget_exceeded' => true,
            'error_rate_pct' => 0.0,
            'mean_db_queries_per_request' => 2.0,
        ]);

        $match = $matches->first(
            fn (array $item): bool => $item['rule_id'] === 'http_budget_exceeded',
        );

        $this->assertNotNull($match);
        $this->assertSame('student_search', $match['context']['workload']);
        $this->assertTrue($match['signals']['budget_exceeded']);
    }
}
