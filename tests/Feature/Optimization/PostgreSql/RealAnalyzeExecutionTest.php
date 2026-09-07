<?php

namespace Tests\Feature\Optimization\PostgreSql;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\QueryMetric;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\SelfHealing\CheckpointRecoveryService;
use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\CheckpointStatus;
use App\Optimization\SelfHealing\CircuitBreaker;
use App\Optimization\SelfHealing\OptimizationTargetLock;
use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use App\Optimization\SelfHealing\SelfHealingStateStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\PostgreSqlAnalyzeEvidence;
use Tests\Support\Optimization\PostgreSqlOptimizationTestCase;
use Tests\Support\Optimization\SequentialMetricSnapshotCapturer;

final class RealAnalyzeExecutionTest extends PostgreSqlOptimizationTestCase
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
    public function s11_real_postgresql_analyze_e2e_through_safe_auto_executor(): void
    {
        Recommendation::query()
            ->where('action_type', 'analyze')
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        $statsBefore = PostgreSqlAnalyzeEvidence::tableStats();
        if ($statsBefore === null) {
            DB::statement('ANALYZE '.PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET);
            $statsBefore = PostgreSqlAnalyzeEvidence::tableStats();
        }
        $this->assertNotNull($statsBefore, 'pg_stat_user_tables must expose validation target');

        QueryMetric::query()->create([
            'query_fingerprint' => 'fp_validation_e2e',
            'query_label' => PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'call_count' => 100,
            'p95_ms' => 500,
            'p99_ms' => 600,
            'captured_at' => now(),
        ]);

        $recommendation = Recommendation::query()->create([
            'recommendation_code' => 'REC-PG-E2E-'.now()->format('His'),
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            'table_name' => PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
            'what' => 'ANALYZE '.PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'why' => 'Real execution validation',
            'confidence' => 0.9,
            'status' => 'pending',
            'evidence' => ['query_fingerprint' => 'fp_validation_e2e'],
        ]);

        $before = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 100.0,
            cacheHitRatio: 0.9,
            errorRatePct: 0.1,
        );
        $after = new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 85.0,
            cacheHitRatio: 0.92,
            errorRatePct: 0.1,
        );

        $this->app->instance(
            \App\Optimization\SelfHealing\MetricSnapshotCapturer::class,
            new SequentialMetricSnapshotCapturer([$before, $before, $after, $after, $after]),
        );

        $this->warmBaseline();
        $this->bindDegradedTelemetry();

        $engine = app(SelfHealingPerformanceEngine::class);
        $result = $engine->runCycle();

        $this->assertSame('heal_applied', $result['outcome'] ?? null, json_encode($result));

        $event = OptimizationEvent::query()
            ->where('action_taken', 'like', '%'.PostgreSqlAnalyzeEvidence::VALIDATION_TABLE.'%')
            ->latest('id')
            ->first();

        $this->assertNotNull($event, 'OptimizationEvent must exist after real ANALYZE');
        $this->assertSame($recommendation->id, (int) $event->recommendation_id);
        $this->assertStringContainsString('ANALYZE', (string) $event->action_taken);
        $this->assertNotNull($event->evidence_before);
        $this->assertNotNull($event->evidence_after);

        $statsAfter = PostgreSqlAnalyzeEvidence::tableStats();
        $this->assertNotNull($statsAfter);
        $this->assertNotNull($statsAfter['last_analyze'] ?? $statsAfter['last_autoanalyze'] ?? null);

        $checkpoint = app(CheckpointService::class)->load($result['checkpoint_id']);
        $this->assertSame(CheckpointStatus::StabilizationStarted->value, $checkpoint['execution_status']);
        $this->assertNotEquals(
            $checkpoint['before_state']['p95_latency_ms'] ?? 100,
            ($result['result']['after_metrics']['p95_latency_ms'] ?? 85),
        );
    }

    #[Test]
    public function s21_crash_after_real_analyze_marks_ambiguous_and_blocks_duplicate(): void
    {
        $recommendation = Recommendation::query()->create([
            'recommendation_code' => 'REC-PG-S21-'.now()->format('His'),
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            'table_name' => PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
            'what' => 'ANALYZE validation target',
            'why' => 'S21 crash validation',
            'confidence' => 0.9,
            'status' => 'pending',
        ]);

        $executor = app(SafeAutoExecutor::class);
        $event = $executor->attempt($recommendation);
        $this->assertNotNull($event, 'Real ANALYZE must execute before crash simulation');

        $checkpoints = app(CheckpointService::class);
        $id = $checkpoints->create([
            'operation_id' => 'OP-S21',
            'incident_id' => 'INC-S21',
            'recommendation_id' => $recommendation->id,
            'target' => PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'action' => 'analyze',
            'before_state' => ['p95_latency_ms' => 100],
            'rollback_supported' => false,
            'event_code' => $event->event_code,
        ]);
        $checkpoints->transition($id, CheckpointStatus::ExecutionStarted, ['recommendation_id' => $recommendation->id]);
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
    public function f4_circuit_breaker_open_blocks_autonomous_execution(): void
    {
        config(['optimization.mode' => 'autonomous']);

        $breaker = app(CircuitBreaker::class);
        $breaker->recordFailure('f4');
        $breaker->recordFailure('f4');
        $breaker->recordFailure('f4');

        $this->assertTrue($breaker->isOpen());

        $this->warmBaseline();
        $this->bindDegradedTelemetry();

        $result = app(SelfHealingPerformanceEngine::class)->runCycle();
        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function f5_target_lock_blocks_concurrent_execution(): void
    {
        $lock = app(OptimizationTargetLock::class);
        $target = PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET;

        $this->assertTrue($lock->acquire($target, PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA, PostgreSqlAnalyzeEvidence::VALIDATION_TABLE));
        $this->assertFalse($lock->acquire($target, PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA, PostgreSqlAnalyzeEvidence::VALIDATION_TABLE));

        $lock->release($target, PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA, PostgreSqlAnalyzeEvidence::VALIDATION_TABLE);
    }

    #[Test]
    public function f1_disallowed_target_is_rejected_by_safe_auto_executor(): void
    {
        config(['optimization.analyze.allowed_targets' => ['public.students']]);

        $recommendation = Recommendation::query()->create([
            'recommendation_code' => 'REC-PG-DENY-'.now()->format('His'),
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            'table_name' => PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
            'what' => 'ANALYZE denied',
            'why' => 'Allowlist test',
            'confidence' => 0.9,
            'status' => 'pending',
        ]);

        $this->assertNull(app(SafeAutoExecutor::class)->attempt($recommendation));
    }

    #[Test]
    public function f2_analyze_timeout_does_not_create_false_success(): void
    {
        config([
            'optimization.analyze.execution_timeout_seconds' => 1,
            'optimization.analyze.allowed_targets' => [PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET],
        ]);

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.database'),
        );

        $lockConnection = new \PDO(
            $dsn,
            (string) config('database.connections.pgsql.username'),
            (string) config('database.connections.pgsql.password'),
        );
        $lockConnection->exec('BEGIN');
        $lockConnection->exec('LOCK TABLE '.PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET.' IN ACCESS EXCLUSIVE MODE');

        try {
            $recommendation = Recommendation::query()->create([
                'recommendation_code' => 'REC-PG-F2-'.now()->format('His'),
                'rule_id' => 'rule_analyze_table',
                'risk_tier' => 1,
                'action_type' => 'analyze',
                'schema_name' => PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
                'table_name' => PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
                'what' => 'ANALYZE timeout test',
                'why' => 'F2 timeout validation',
                'confidence' => 0.9,
                'status' => 'pending',
            ]);

            $eventsBefore = OptimizationEvent::query()->count();

            $result = app(SafeAutoExecutor::class)->attempt($recommendation);

            $this->assertNull($result);
            $this->assertSame($eventsBefore, OptimizationEvent::query()->count());
            $this->assertSame('pending', $recommendation->fresh()->status);
        } finally {
            $lockConnection->exec('ROLLBACK');
        }
    }

    #[Test]
    public function allowlist_empty_blocks_even_with_valid_target(): void
    {
        config(['optimization.analyze.allowed_targets' => []]);

        $recommendation = Recommendation::query()->create([
            'recommendation_code' => 'REC-PG-EMPTY-'.now()->format('His'),
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            'table_name' => PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
            'what' => 'ANALYZE',
            'why' => 'empty allowlist',
            'confidence' => 0.9,
            'status' => 'pending',
        ]);

        $this->assertNull(app(SafeAutoExecutor::class)->attempt($recommendation));
    }

    #[Test]
    public function production_config_file_default_remains_observe(): void
    {
        $defaults = require config_path('optimization.php');
        $this->assertSame('observe', $defaults['mode'] ?? 'observe');
    }
}
