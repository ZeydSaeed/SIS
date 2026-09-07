<?php

namespace Tests\Unit\Optimization;

use App\Intelligence\Models\Recommendation;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Execution\IsolatedOptimizationRunner;
use App\Optimization\Execution\OptimizationOperationRegistry;
use App\Optimization\Gates\ArchitectureOptimizationGate;
use App\Optimization\Gates\CrossMetricGuard;
use App\Optimization\Memory\OptimizationHistoryRecorder;
use App\Optimization\Rollback\NonReversibleRollbackStrategy;
use App\Optimization\Rollback\RollbackManager;
use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\MetricSnapshotCapturer;
use App\Optimization\SelfHealing\SelfHealingEventLogger;
use App\Optimization\Analysis\OptimizationScorer;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\FakeMetricSnapshotCapturer;
use Tests\Support\Optimization\TestAnalyzeOperation;
use Tests\TestCase;

class IsolatedOptimizationRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'optimization.history_path' => storage_path('framework/testing/optimization-history'),
            'optimization.checkpoints_path' => storage_path('framework/testing/optimization-checkpoints'),
            'optimization.measurement.post_delay_seconds' => 0,
            'optimization.gates.architecture_validate_before_code_change' => false,
        ]);
    }

    private function makeRunner(
        OptimizationOperationRegistry $registry,
        MetricSnapshotCapturer $capturer,
    ): IsolatedOptimizationRunner {
        return new IsolatedOptimizationRunner(
            $registry,
            new OptimizationScorer,
            app(ArchitectureOptimizationGate::class),
            new CrossMetricGuard,
            new OptimizationHistoryRecorder,
            $capturer,
            new CheckpointService,
            new RollbackManager(new NonReversibleRollbackStrategy, new SelfHealingEventLogger),
            new SelfHealingEventLogger,
        );
    }

    #[Test]
    public function s5_it_rejects_when_cross_metric_guard_detects_regression(): void
    {
        $before = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 100.0,
            cacheHitRatio: 0.9,
        );

        $after = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 200.0,
            cacheHitRatio: 0.9,
        );

        $registry = new OptimizationOperationRegistry;
        $registry->register(new TestAnalyzeOperation([
            'executed' => true,
            'event' => (object) ['event_code' => 'EVT-001'],
            'reason' => null,
        ]));

        $capturer = new FakeMetricSnapshotCapturer($after);

        $runner = $this->makeRunner($registry, $capturer);

        $recommendation = new Recommendation([
            'id' => 1,
            'recommendation_code' => 'REC-RUN-001',
            'action_type' => 'analyze',
            'confidence' => 0.9,
            'risk_tier' => 1,
            'table_name' => 'students',
            'correlation_id' => 'INC-001',
            'why' => 'test',
            'what' => 'ANALYZE',
            'expected_impact' => ['expected_improvement_pct' => 10],
        ]);

        $result = $runner->run($recommendation, $before);

        $this->assertFalse($result['executed']);
        $this->assertSame('REJECTED', $result['decision']);
        $this->assertNotEmpty($result['regressions']);
    }

    #[Test]
    public function s8_it_blocks_when_critical_metrics_are_unavailable(): void
    {
        $before = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: null,
            cacheHitRatio: null,
        );

        $registry = new OptimizationOperationRegistry;
        $registry->register(new TestAnalyzeOperation);

        $runner = $this->makeRunner($registry, new FakeMetricSnapshotCapturer($before));

        $recommendation = new Recommendation([
            'action_type' => 'analyze',
            'confidence' => 0.9,
            'risk_tier' => 1,
            'why' => 'test',
            'what' => 'ANALYZE',
            'expected_impact' => ['expected_improvement_pct' => 10],
        ]);

        $result = $runner->run($recommendation, $before);

        $this->assertFalse($result['executed']);
        $this->assertStringContainsString('Critical metrics unavailable', $result['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
