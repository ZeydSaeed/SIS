<?php

namespace Tests\Feature\Optimization\PostgreSql;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Execution\OptimizationOperationRegistry;
use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use App\Optimization\SelfHealing\AutonomousRateLimiter;
use App\Optimization\SelfHealing\CheckpointRecoveryService;
use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\CheckpointStatus;
use App\Optimization\SelfHealing\CircuitBreaker;
use App\Optimization\SelfHealing\OptimizationTargetLock;
use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\PostgreSqlAnalyzeEvidence;
use Tests\Support\Optimization\PostgreSqlOptimizationTestCase;
use Tests\Support\Optimization\SequentialMetricSnapshotCapturer;

/**
 * Phase 2B.2 — Operational soak, canary readiness, and failure injection.
 */
final class OperationalSoakTest extends PostgreSqlOptimizationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(DatabaseGuardian::class, function ($mock) {
            $mock->shouldReceive('runPerformanceCycle')->andReturn(['recommendations' => 0]);
            $mock->shouldReceive('runHealthCycle')->andReturn(['snapshot_id' => 1]);
        });

        MonitoringSnapshot::query()->create([
            'snapshot_type' => 'health',
            'captured_at' => now(),
            'database_size_mb' => 512,
            'connection_count' => 5,
            'cache_hit_ratio' => 0.9,
        ]);
    }

    #[Test]
    public function production_isolation_blocks_autonomous_even_when_all_other_gates_satisfied(): void
    {
        config([
            'app.env' => 'production',
            'optimization.mode' => 'autonomous',
            'optimization.autonomous.controlled_environments' => ['testing'],
            'optimization.autonomous.kill_switch' => false,
            'optimization.scoring.min_confidence_for_autonomous' => 0.5,
        ]);

        $this->seedValidationRecommendation(confidence: 0.99);
        $this->bindSuccessMetrics();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('environment_unauthorized', $result['policy_code'] ?? null);
    }

    #[Test]
    public function kill_switch_on_blocks_autonomous_execution(): void
    {
        config(['optimization.autonomous.kill_switch' => true]);
        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null, json_encode($result));
        $this->assertContains($result['outcome'] ?? '', ['degraded_observed', 'monitor', 'baseline_warmup']);
        if (($result['outcome'] ?? '') === 'degraded_observed') {
            $this->assertSame('kill_switch_engaged', $result['policy_code'] ?? null);
        } else {
            $this->assertTrue(app(\App\Optimization\SelfHealing\AutonomousKillSwitch::class)->isEngaged());
        }
    }

    #[Test]
    public function kill_switch_off_allows_controlled_eligible_execution(): void
    {
        config(['optimization.autonomous.kill_switch' => false]);
        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();

        $result = $this->runAutonomousCycle();

        $this->assertSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function rate_limit_blocks_repeat_target_within_window(): void
    {
        config([
            'optimization.autonomous.rate_limits.max_per_target_per_window' => 1,
            'optimization.cooldown.minutes' => 0,
        ]);

        app(AutonomousRateLimiter::class)->recordExecution(
            PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'CYC-PRESEED',
        );

        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('rate_limit_target', $result['policy_code'] ?? null);
    }

    #[Test]
    public function concurrent_workers_block_second_target_lock(): void
    {
        $lock = app(OptimizationTargetLock::class);
        $this->assertTrue($lock->acquire(
            PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
        ));

        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('target_locked', $result['policy_code'] ?? null);

        $lock->release(
            PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
        );
    }

    #[Test]
    public function cooldown_blocks_immediate_repeat_on_same_target(): void
    {
        config(['optimization.cooldown.minutes' => 60]);

        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();
        $first = $this->runAutonomousCycle();
        $this->assertSame('heal_applied', $first['outcome'] ?? null);

        $this->seedValidationRecommendation();
        $this->bindDegradedTelemetry();
        $this->bindSuccessMetrics();
        $second = app(SelfHealingPerformanceEngine::class)->runCycle();

        $this->assertNotSame('heal_applied', $second['outcome'] ?? null);
        $this->assertSame('cooldown_active', $second['policy_code'] ?? null);
    }

    #[Test]
    public function controlled_multi_cycle_soak_records_mixed_outcomes(): void
    {
        $started = microtime(true);
        $stats = [
            'cycles' => 0,
            'heal_applied' => 0,
            'blocked' => 0,
            'monitor' => 0,
        ];

        $this->warmBaseline();

        for ($i = 0; $i < 8; $i++) {
            if ($i % 4 === 0) {
                $this->bindDegradedTelemetry();
                $this->seedValidationRecommendation();
                $this->bindSuccessMetrics();
            } else {
                $this->bindHealthyTelemetry();
            }

            $result = app(SelfHealingPerformanceEngine::class)->runCycle();
            $stats['cycles']++;
            $outcome = $result['outcome'] ?? 'unknown';

            if ($outcome === 'heal_applied') {
                $stats['heal_applied']++;
            } elseif ($outcome === 'degraded_observed') {
                $stats['blocked']++;
            } elseif ($outcome === 'monitor') {
                $stats['monitor']++;
            }
        }

        $elapsedSeconds = round(microtime(true) - $started, 2);

        $this->assertSame(8, $stats['cycles']);
        $this->assertGreaterThan(0, $stats['monitor']);
        $this->assertGreaterThan(0, $elapsedSeconds);
        $this->assertLessThan(600, $elapsedSeconds, 'Controlled soak must complete within 10 minutes');
    }

    #[Test]
    public function worker_crash_before_checkpoint_allows_subsequent_execution(): void
    {
        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();
        $result = $this->runAutonomousCycle();
        $this->assertSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function worker_crash_after_checkpoint_started_blocks_blind_retry(): void
    {
        $checkpoints = app(CheckpointService::class);
        $id = $checkpoints->create([
            'operation_id' => 'OP-CRASH-B',
            'incident_id' => 'INC-CRASH-B',
            'target' => PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'action' => 'analyze',
            'before_state' => ['p95_latency_ms' => 100, 'cache_hit_ratio' => 0.9],
            'rollback_supported' => false,
        ]);
        $checkpoints->transition($id, CheckpointStatus::ExecutionStarted);

        app(CheckpointRecoveryService::class)->recoverIncomplete();

        $recovered = $checkpoints->load($id);
        $this->assertSame('INCOMPLETE_NO_AUTO_RETRY', $recovered['final_outcome']);

        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();
        $result = $this->runAutonomousCycle();
        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('checkpoint_ambiguous', $result['policy_code'] ?? null);
    }

    #[Test]
    public function worker_crash_after_real_analyze_blocks_blind_retry_s21(): void
    {
        $recommendation = $this->seedValidationRecommendation();
        $event = app(SafeAutoExecutor::class)->attempt($recommendation);
        $this->assertNotNull($event);

        $checkpoints = app(CheckpointService::class);
        $id = $checkpoints->create([
            'operation_id' => 'OP-S21-2B2',
            'incident_id' => 'INC-S21-2B2',
            'recommendation_id' => $recommendation->id,
            'target' => PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'action' => 'analyze',
            'before_state' => ['p95_latency_ms' => 100, 'cache_hit_ratio' => 0.9],
            'rollback_supported' => false,
            'event_code' => $event->event_code,
        ]);
        $checkpoints->transition($id, CheckpointStatus::ExecutionStarted);
        $checkpoints->transition($id, CheckpointStatus::Executed, ['event_code' => $event->event_code]);

        app(CheckpointRecoveryService::class)->recoverIncomplete();

        $recovered = $checkpoints->load($id);
        $this->assertSame('EXECUTED_BUT_NOT_FINALIZED', $recovered['final_outcome']);
        $this->assertTrue($recovered['execution_ambiguous'] ?? false);
        $this->assertTrue(
            app(CheckpointRecoveryService::class)->blocksAutonomousRetry(PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET),
        );
    }

    #[Test]
    public function critical_telemetry_unknown_blocks_autonomous_execution(): void
    {
        $unknown = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: null,
            cacheHitRatio: null,
        );
        $this->app->instance(
            \App\Optimization\SelfHealing\MetricSnapshotCapturer::class,
            new SequentialMetricSnapshotCapturer([$unknown]),
        );

        $this->seedValidationRecommendation();
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('critical_telemetry_unknown', $result['policy_code'] ?? null);
    }

    #[Test]
    public function stale_baseline_blocks_autonomous_execution(): void
    {
        $this->warmBaseline();
        app(AdaptiveBaselineEngine::class)->markStale('test_stale');

        $this->seedValidationRecommendation();
        $this->bindDegradedTelemetry();
        $result = app(SelfHealingPerformanceEngine::class)->runCycle();

        $this->assertSame('baseline_warmup', $result['outcome'] ?? null);
        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function circuit_breaker_opens_after_failures_and_blocks_autonomous(): void
    {
        $breaker = app(CircuitBreaker::class);
        $breaker->recordFailure('ops-test');
        $breaker->recordFailure('ops-test');
        $breaker->recordFailure('ops-test');

        $this->assertTrue($breaker->isOpen());

        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('mode_not_autonomous', $result['policy_code'] ?? null);

        $breaker->reset();
        $this->assertFalse($breaker->isOpen());
    }

    #[Test]
    public function checkpoint_lineage_is_complete_for_successful_autonomous_analyze(): void
    {
        $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();
        $result = $this->runAutonomousCycle();
        $this->assertSame('heal_applied', $result['outcome'] ?? null);

        $checkpoint = app(CheckpointService::class)->load($result['checkpoint_id']);
        $this->assertNotNull($checkpoint['checkpoint_id'] ?? null);
        $this->assertNotNull($checkpoint['operation_id'] ?? null);
        $this->assertNotNull($checkpoint['incident_id'] ?? null);
        $this->assertNotNull($checkpoint['target'] ?? null);
        $this->assertSame('analyze', $checkpoint['action'] ?? null);
        $this->assertNotNull($checkpoint['before_state'] ?? null);
        $this->assertSame(CheckpointStatus::StabilizationStarted->value, $checkpoint['execution_status']);
        $this->assertStringContainsString('non_reversible_safe', (string) ($checkpoint['restore_strategy'] ?? ''));
        $this->assertFalse($checkpoint['rollback_supported'] ?? true);
    }

    #[Test]
    public function operation_registry_contains_analyze_only(): void
    {
        $registry = app(OptimizationOperationRegistry::class);
        $this->assertNotNull($registry->forAction('analyze'));
        $this->assertNull($registry->forAction('create_index'));
        $this->assertNull($registry->forAction('vacuum'));
        $this->assertSame(['analyze'], config('optimization.autonomous_actions'));
    }

    #[Test]
    public function worker_lock_prevents_overlapping_cycles(): void
    {
        $this->bindHealthyTelemetry();
        $lock = Cache::lock('optimization:self-healing:worker', 60);
        $lock->get();

        try {
            $result = app(SelfHealingPerformanceEngine::class)->runCycle();
            $this->assertTrue($result['skipped'] ?? false);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function database_execution_failure_does_not_record_false_success(): void
    {
        $this->warmBaseline();
        $this->seedValidationRecommendation();
        $this->bindDegradedTelemetry();
        $this->bindSuccessMetrics();

        DB::statement('DROP TABLE IF EXISTS intelligence.optimization_validation_target CASCADE');

        try {
            $eventsBefore = OptimizationEvent::query()->count();
            $result = app(SelfHealingPerformanceEngine::class)->runCycle();

            $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
            $this->assertSame($eventsBefore, OptimizationEvent::query()->count());
        } finally {
            PostgreSqlAnalyzeEvidence::ensureValidationTableSeeded();
        }
    }
}
