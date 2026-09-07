<?php

namespace Tests\Feature\Optimization\PostgreSql;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Learning\ConfidenceEngine;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\QueryMetric;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\SelfHealing\AutonomousExecutionPolicy;
use App\Optimization\SelfHealing\CheckpointRecoveryService;
use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\CheckpointStatus;
use App\Optimization\SelfHealing\CircuitBreaker;
use App\Optimization\SelfHealing\OptimizationTargetLock;
use App\Optimization\SelfHealing\RecommendationRanker;
use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\PostgreSqlAnalyzeEvidence;
use Tests\Support\Optimization\PostgreSqlOptimizationTestCase;
use Tests\Support\Optimization\SequentialMetricSnapshotCapturer;

/**
 * Phase 2B.1 — Controlled Autonomous ANALYZE validation matrix (A1–A16).
 *
 * Synthetic anomaly triggers are used for deterministic RCA; real PostgreSQL ANALYZE
 * execution is verified via pg_stat_user_tables and OptimizationEvent evidence.
 */
final class ControlledAutonomousAnalyzeTest extends PostgreSqlOptimizationTestCase
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
    public function a1_observe_mode_blocks_autonomous_analyze(): void
    {
        config(['optimization.mode' => 'observe']);
        $this->seedValidationRecommendation();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('mode_not_autonomous', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a2_missing_allowlist_blocks_autonomous_analyze(): void
    {
        config(['optimization.analyze.allowed_targets' => []]);
        $this->seedValidationRecommendation();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('allowlist_invalid', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a3_unauthorized_target_blocks_autonomous_analyze(): void
    {
        config(['optimization.analyze.allowed_targets' => ['public.students']]);
        $this->seedValidationRecommendation();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('target_not_allowlisted', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a4_unauthorized_environment_blocks_autonomous_analyze(): void
    {
        config([
            'app.env' => 'production',
            'optimization.mode' => 'autonomous',
            'optimization.autonomous.controlled_environments' => ['testing'],
        ]);
        $this->seedValidationRecommendation();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertContains($result['policy_code'] ?? '', ['environment_unauthorized', 'mode_not_autonomous', 'baseline_incompatible']);
    }

    #[Test]
    public function a5_circuit_breaker_open_blocks_autonomous_analyze(): void
    {
        $breaker = app(CircuitBreaker::class);
        $breaker->recordFailure('a5');
        $breaker->recordFailure('a5');
        $breaker->recordFailure('a5');
        $this->assertTrue($breaker->isOpen());

        $this->seedValidationRecommendation();
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('mode_not_autonomous', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a6_target_lock_conflict_blocks_autonomous_analyze(): void
    {
        $lock = app(OptimizationTargetLock::class);
        $lock->acquire(
            PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
        );

        $this->seedValidationRecommendation();
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
    public function a7_incompatible_baseline_blocks_autonomous_analyze(): void
    {
        $this->warmBaseline();

        $this->bindTelemetry([
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => 800,
            'p99_latency_ms' => 900,
            'cpu_pct' => 90,
            'memory_mb' => 256,
            'db_queries_per_request' => 50,
            'cache_hit_ratio' => 0.3,
            'error_rate_pct' => null,
            'error_rate_status' => 'UNKNOWN',
            'context_fingerprint' => ['environment' => 'production', 'cpu_cores' => 99],
        ]);

        $this->seedValidationRecommendation();
        $result = app(SelfHealingPerformanceEngine::class)->runCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function a8_critical_telemetry_unknown_blocks_autonomous_analyze(): void
    {
        $this->bindHealthyMetrics();
        $this->app->instance(
            \App\Optimization\SelfHealing\MetricSnapshotCapturer::class,
            new SequentialMetricSnapshotCapturer([
                new MetricSnapshot(capturedAt: now()->toIso8601String(), p95LatencyMs: null, cacheHitRatio: null),
            ]),
        );

        $this->seedValidationRecommendation();
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('critical_telemetry_unknown', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a9_ambiguous_checkpoint_blocks_autonomous_analyze(): void
    {
        $recommendation = $this->seedValidationRecommendation();
        $executor = app(SafeAutoExecutor::class);
        $event = $executor->attempt($recommendation);
        $this->assertNotNull($event);

        $checkpoints = app(CheckpointService::class);
        $id = $checkpoints->create([
            'operation_id' => 'OP-A9',
            'incident_id' => 'INC-A9',
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

        $this->assertTrue(
            app(CheckpointRecoveryService::class)->blocksAutonomousRetry(PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET),
        );

        $result = $this->runAutonomousCycle();
        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('checkpoint_ambiguous', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a10_insufficient_confidence_blocks_autonomous_analyze(): void
    {
        config(['optimization.scoring.min_confidence_for_autonomous' => 0.95]);
        $this->seedValidationRecommendation(confidence: 0.5);

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('insufficient_confidence', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a11_controlled_autonomous_analyze_e2e_executes_real_postgresql_analyze(): void
    {
        Recommendation::query()
            ->where('action_type', 'analyze')
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        $statsBefore = PostgreSqlAnalyzeEvidence::tableStats();
        $this->assertNotNull($statsBefore);

        $recommendation = $this->seedValidationRecommendation();
        $this->bindSuccessMetrics();

        $result = $this->runAutonomousCycle();

        $this->assertSame('heal_applied', $result['outcome'] ?? null, json_encode($result));

        $event = OptimizationEvent::query()
            ->where('action_taken', 'like', '%'.PostgreSqlAnalyzeEvidence::VALIDATION_TABLE.'%')
            ->latest('id')
            ->first();

        $this->assertNotNull($event, 'Real PostgreSQL ANALYZE must produce OptimizationEvent');
        $this->assertSame($recommendation->id, (int) $event->recommendation_id);
        $this->assertStringContainsString('ANALYZE', (string) $event->action_taken);

        $statsAfter = PostgreSqlAnalyzeEvidence::tableStats();
        $this->assertNotNull($statsAfter['last_analyze'] ?? $statsAfter['last_autoanalyze'] ?? null);

        $checkpoint = app(CheckpointService::class)->load($result['checkpoint_id']);
        $this->assertSame(CheckpointStatus::StabilizationStarted->value, $checkpoint['execution_status']);
    }

    #[Test]
    public function a12_guard_failure_after_real_analyze_is_not_considered_successful(): void
    {
        $this->seedValidationRecommendation();

        $before = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 100.0,
            cacheHitRatio: 0.9,
            errorRatePct: 0.1,
        );
        $afterBad = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 500.0,
            cacheHitRatio: 0.2,
            errorRatePct: 5.0,
        );

        $this->app->instance(
            \App\Optimization\SelfHealing\MetricSnapshotCapturer::class,
            new SequentialMetricSnapshotCapturer([$before, $before, $before, $afterBad, $afterBad]),
        );

        $eventsBefore = OptimizationEvent::query()->count();
        $result = $this->runAutonomousCycle();

        $this->assertSame('heal_failed', $result['outcome'] ?? null);
        $this->assertGreaterThan($eventsBefore, OptimizationEvent::query()->count(), 'Real ANALYZE may execute before guard rejection');
        $this->assertSame('REJECTED', $result['result']['decision'] ?? null);
    }

    #[Test]
    public function a13_crash_after_real_analyze_blocks_blind_retry(): void
    {
        $recommendation = $this->seedValidationRecommendation();
        $event = app(SafeAutoExecutor::class)->attempt($recommendation);
        $this->assertNotNull($event);

        $checkpoints = app(CheckpointService::class);
        $id = $checkpoints->create([
            'operation_id' => 'OP-A13',
            'incident_id' => 'INC-A13',
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

        $result = $this->runAutonomousCycle();
        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('checkpoint_ambiguous', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a14_learning_escalation_does_not_expand_autonomous_privilege(): void
    {
        config([
            'optimization.scoring.max_risk_tier_autonomous' => 1,
            'optimization.analyze.allowed_targets' => [PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET],
        ]);

        $ranker = new RecommendationRanker(app(ConfidenceEngine::class));
        $this->assertSame(1, $ranker->maxAutonomousRiskTier());

        $policy = app(AutonomousExecutionPolicy::class);
        $this->assertTrue($policy->allowlistConfigurationValid());

        config(['optimization.analyze.allowed_targets' => ['*']]);
        $this->assertFalse($policy->allowlistConfigurationValid());

        $this->assertSame(['analyze'], config('optimization.autonomous_actions'));
        $this->assertSame(['analyze'], config('optimization.low_risk_auto_actions'));
    }

    #[Test]
    public function a15_wildcard_allowlist_blocks_autonomous_analyze(): void
    {
        config(['optimization.analyze.allowed_targets' => ['*']]);
        $this->seedValidationRecommendation();

        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('allowlist_invalid', $result['policy_code'] ?? null);
    }

    #[Test]
    public function a16_sql_injection_target_is_blocked(): void
    {
        config(['optimization.analyze.allowed_targets' => [PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET]]);

        Recommendation::query()
            ->where('action_type', 'analyze')
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        QueryMetric::query()->create([
            'query_fingerprint' => 'fp_injection',
            'query_label' => 'intelligence"; DROP TABLE users; --',
            'call_count' => 100,
            'p95_ms' => 500,
            'p99_ms' => 600,
            'captured_at' => now(),
        ]);

        Recommendation::query()->create([
            'recommendation_code' => 'REC-INJECT-'.now()->format('His'),
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => 'intelligence"; DROP TABLE users; --',
            'table_name' => 'optimization_validation_target',
            'what' => 'ANALYZE injection attempt',
            'why' => 'Security test',
            'confidence' => 0.9,
            'status' => 'pending',
            'evidence' => ['query_fingerprint' => 'fp_injection'],
        ]);

        $injection = Recommendation::query()->where('recommendation_code', 'like', 'REC-INJECT-%')->first();
        $this->assertNotNull($injection);
        $this->assertNull(app(SafeAutoExecutor::class)->attempt($injection));

        $this->bindSuccessMetrics();
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function production_observe_mode_blocks_even_with_high_confidence_and_allowlisted_target(): void
    {
        config([
            'app.env' => 'production',
            'optimization.mode' => 'observe',
            'optimization.analyze.allowed_targets' => [PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET],
            'optimization.autonomous.controlled_environments' => ['production'],
        ]);

        $defaults = require config_path('optimization.php');
        $this->assertSame('observe', $defaults['mode']);

        $this->seedValidationRecommendation(confidence: 0.99);
        $result = $this->runAutonomousCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertSame('mode_not_autonomous', $result['policy_code'] ?? null);
    }

    private function bindHealthyMetrics(): void
    {
        $healthy = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 80.0,
            cacheHitRatio: 0.95,
            errorRatePct: 0.1,
        );

        $this->app->instance(
            \App\Optimization\SelfHealing\MetricSnapshotCapturer::class,
            new SequentialMetricSnapshotCapturer([$healthy]),
        );
    }
}
