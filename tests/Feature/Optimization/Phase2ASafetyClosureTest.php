<?php

namespace Tests\Feature\Optimization;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\QueryMetric;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Execution\OptimizationOperationRegistry;
use App\Optimization\SelfHealing\CheckpointService;
use App\Optimization\SelfHealing\CheckpointStatus;
use App\Optimization\SelfHealing\EnvironmentProfileService;
use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use App\Optimization\SelfHealing\TelemetryCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\FakeTelemetryCollector;
use Tests\Support\Optimization\SequentialMetricSnapshotCapturer;
use Tests\Support\Optimization\TestAnalyzeOperation;
use Tests\TestCase;
use App\Optimization\Contracts\MetricSnapshot;

class Phase2ASafetyClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'optimization.mode' => 'autonomous',
            'optimization.environments.testing.max_risk_tier_autonomous' => 1,
            'optimization.autonomous_actions' => ['analyze'],
            'optimization.analyze.allowed_targets' => ['public.students'],
            'optimization.autonomous.require_controlled_environment' => true,
            'optimization.autonomous.controlled_environments' => ['testing'],
            'optimization.anomaly.consecutive_observations_required' => 1,
            'optimization.anomaly.sustained_degradation_minutes' => 0,
            'optimization.baseline.min_healthy_observations_to_adapt' => 1,
            'optimization.scoring.min_confidence_for_autonomous' => 0.5,
            'optimization.measurement.post_delay_seconds' => 0,
            'optimization.data_scale.block_autonomous_on_unknown' => false,
            'optimization.gates.architecture_validate_before_code_change' => false,
            'optimization.state_path' => storage_path('framework/testing/optimization-state-phase2a'),
            'optimization.adaptive_baseline_path' => storage_path('framework/testing/adaptive-baseline-phase2a.json'),
            'optimization.environment_profile_path' => storage_path('framework/testing/environment-profile-phase2a.json'),
            'optimization.checkpoints_path' => storage_path('framework/testing/checkpoints-phase2a'),
            'optimization.reports_path' => storage_path('framework/testing/optimization-reports-phase2a'),
            'optimization.history_path' => storage_path('framework/testing/optimization-history-phase2a'),
        ]);

        File::deleteDirectory(config('optimization.state_path'));
        File::deleteDirectory(config('optimization.checkpoints_path'));
        File::delete(config('optimization.adaptive_baseline_path'));
        File::delete(config('optimization.environment_profile_path'));

        app(EnvironmentProfileService::class)->capture();

        MonitoringSnapshot::query()->create([
            'snapshot_type' => 'health',
            'captured_at' => now(),
            'database_size_mb' => 512,
            'connection_count' => 5,
            'cache_hit_ratio' => 0.9,
        ]);

        $this->mock(DatabaseGuardian::class, function ($mock) {
            $mock->shouldReceive('runPerformanceCycle')->andReturn(['recommendations' => 0]);
            $mock->shouldReceive('runHealthCycle')->andReturn(['snapshot_id' => 1]);
        });

        $this->app->singleton(OptimizationOperationRegistry::class, function () {
            $registry = new OptimizationOperationRegistry;
            $registry->register(new TestAnalyzeOperation([
                'executed' => true,
                'event' => (object) ['event_code' => 'EVT-E2E-001'],
                'reason' => null,
            ]));

            return $registry;
        });
    }

    #[Test]
    public function s10_worker_crash_recovery_records_incomplete_checkpoint_without_auto_retry(): void
    {
        $checkpoints = app(CheckpointService::class);
        $id = $checkpoints->create([
            'operation_id' => 'OP-CRASH',
            'incident_id' => 'INC-CRASH',
            'target' => 'public.students',
            'action' => 'analyze',
            'before_state' => ['p95_latency_ms' => 100],
            'rollback_supported' => false,
        ]);

        $checkpoints->transition($id, CheckpointStatus::ExecutionStarted);

        $this->bindHealthyTelemetry();

        $engine = app(SelfHealingPerformanceEngine::class);
        $engine->runCycle();

        $recovered = $checkpoints->load($id);
        $this->assertSame(CheckpointStatus::RecoveryRecorded->value, $recovered['execution_status']);
        $this->assertSame('INCOMPLETE_NO_AUTO_RETRY', $recovered['final_outcome']);
        $this->assertSame('not_applicable', $recovered['rollback_status']);
    }

    #[Test]
    public function s11_full_autonomous_e2e_executes_analyze_with_checkpoint_lifecycle(): void
    {
        QueryMetric::query()->create([
            'query_fingerprint' => 'fp_students_e2e',
            'query_label' => 'public.students',
            'call_count' => 100,
            'p95_ms' => 500,
            'p99_ms' => 600,
            'captured_at' => now(),
        ]);

        Recommendation::query()->create([
            'recommendation_code' => 'REC-E2E-001',
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => 'public',
            'table_name' => 'students',
            'what' => 'ANALYZE students',
            'why' => 'Stale statistics',
            'confidence' => 0.9,
            'status' => 'pending',
            'evidence' => ['query_fingerprint' => 'fp_students_e2e'],
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
        $this->assertArrayHasKey('checkpoint_id', $result);

        $checkpoint = app(CheckpointService::class)->load($result['checkpoint_id']);
        $this->assertNotNull($checkpoint);
        $this->assertSame(CheckpointStatus::StabilizationStarted->value, $checkpoint['execution_status']);
        $this->assertNotNull($checkpoint['before_state']);
        $this->assertNotNull($checkpoint['after_state'] ?? $result['result']['after_metrics'] ?? null);
        $this->assertNotEquals(
            $checkpoint['before_state']['p95_latency_ms'] ?? 100,
            ($result['result']['after_metrics']['p95_latency_ms'] ?? $after->p95LatencyMs),
        );
    }

    #[Test]
    public function s13_environment_change_invalidates_baseline_and_blocks_autonomous_action(): void
    {
        $this->warmBaseline();

        $env = app(EnvironmentProfileService::class);
        $current = $env->current();
        $current['php_version'] = '99.0.0';
        File::put(config('optimization.environment_profile_path'), json_encode($current));

        config(['optimization.mode' => 'autonomous']);
        $this->bindDegradedTelemetry();

        $engine = app(SelfHealingPerformanceEngine::class);
        $result = $engine->runCycle();

        $this->assertSame('baseline_warmup', $result['outcome'], json_encode($result));
        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function s14_context_mismatch_prevents_autonomous_action(): void
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

        config(['optimization.mode' => 'autonomous']);

        $engine = app(SelfHealingPerformanceEngine::class);
        $result = $engine->runCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function s15_data_scale_unknown_blocks_when_configured(): void
    {
        config([
            'optimization.mode' => 'autonomous',
            'optimization.data_scale.block_autonomous_on_unknown' => true,
        ]);

        MonitoringSnapshot::query()->delete();

        $this->warmBaseline();
        $this->bindDegradedTelemetry();

        $engine = app(SelfHealingPerformanceEngine::class);
        $result = $engine->runCycle();

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
    }

    #[Test]
    public function s18_analyze_rollback_is_explicitly_non_reversible(): void
    {
        $manager = app(\App\Optimization\Rollback\RollbackManager::class);

        $this->assertFalse($manager->isRollbackSupported('analyze'));
        $this->assertSame(
            'non_reversible_safe — ANALYZE refreshes planner statistics only',
            $manager->restoreStrategy('analyze'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindTelemetry(array $payload): void
    {
        $this->app->instance(TelemetryCollector::class, new FakeTelemetryCollector($payload));
    }

    private function bindHealthyTelemetry(): void
    {
        $this->bindTelemetry([
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => 80,
            'p99_latency_ms' => 120,
            'cpu_pct' => 20,
            'memory_mb' => 64,
            'memory_pct' => 10,
            'db_queries_per_request' => 3,
            'cache_hit_ratio' => 0.95,
            'error_rate_pct' => null,
            'error_rate_status' => 'UNKNOWN',
            'context_fingerprint' => ['environment' => 'testing', 'cpu_cores' => 4, 'memory_limit_mb' => 512],
        ]);
    }

    private function bindDegradedTelemetry(): void
    {
        $this->bindTelemetry([
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => 800,
            'p99_latency_ms' => 900,
            'cpu_pct' => 90,
            'memory_mb' => 256,
            'memory_pct' => 50,
            'db_queries_per_request' => 50,
            'cache_hit_ratio' => 0.3,
            'error_rate_pct' => null,
            'error_rate_status' => 'UNKNOWN',
            'context_fingerprint' => ['environment' => 'testing', 'cpu_cores' => 4, 'memory_limit_mb' => 512],
        ]);
    }

    private function warmBaseline(): void
    {
        $this->bindHealthyTelemetry();
        $engine = app(SelfHealingPerformanceEngine::class);
        for ($i = 0; $i < 3; $i++) {
            $engine->runCycle();
        }
    }
}
