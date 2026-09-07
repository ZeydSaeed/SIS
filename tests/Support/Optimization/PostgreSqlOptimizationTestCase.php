<?php

namespace Tests\Support\Optimization;

use App\Database\SchemaHelper;
use App\Intelligence\Models\QueryMetric;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\SelfHealing\CircuitBreaker;
use App\Optimization\SelfHealing\EnvironmentProfileService;
use App\Optimization\SelfHealing\MetricSnapshotCapturer;
use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use App\Optimization\SelfHealing\TelemetryCollector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

abstract class PostgreSqlOptimizationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for real ANALYZE validation.');
        }

        config([
            'optimization.mode' => 'autonomous',
            'optimization.environments.testing.max_risk_tier_autonomous' => 1,
            'optimization.autonomous_actions' => ['analyze'],
            'optimization.analyze.allowed_targets' => [PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET],
            'optimization.autonomous.require_controlled_environment' => true,
            'optimization.autonomous.controlled_environments' => ['testing'],
            'optimization.autonomous.kill_switch' => false,
            'optimization.autonomous.rate_limits.max_per_cycle' => 1,
            'optimization.autonomous.rate_limits.max_per_target_per_window' => 10,
            'optimization.autonomous.rate_limits.max_global_per_window' => 50,
            'optimization.cooldown.minutes' => 0,
            'optimization.anomaly.consecutive_observations_required' => 1,
            'optimization.anomaly.sustained_degradation_minutes' => 0,
            'optimization.baseline.min_healthy_observations_to_adapt' => 1,
            'optimization.scoring.min_confidence_for_autonomous' => 0.5,
            'optimization.measurement.post_delay_seconds' => 0,
            'optimization.data_scale.block_autonomous_on_unknown' => false,
            'optimization.gates.architecture_validate_before_code_change' => false,
            'optimization.state_path' => storage_path('framework/testing/optimization-state-pgsql'),
            'optimization.adaptive_baseline_path' => storage_path('framework/testing/adaptive-baseline-pgsql.json'),
            'optimization.environment_profile_path' => storage_path('framework/testing/environment-profile-pgsql.json'),
            'optimization.checkpoints_path' => storage_path('framework/testing/checkpoints-pgsql'),
            'optimization.reports_path' => storage_path('framework/testing/optimization-reports-pgsql'),
            'optimization.history_path' => storage_path('framework/testing/optimization-history-pgsql'),
        ]);

        File::deleteDirectory(config('optimization.state_path'));
        File::deleteDirectory(config('optimization.checkpoints_path'));
        File::delete(config('optimization.adaptive_baseline_path'));
        File::delete(config('optimization.environment_profile_path'));

        Cache::flush();
        app(CircuitBreaker::class)->reset();

        app(EnvironmentProfileService::class)->capture();
        PostgreSqlAnalyzeEvidence::ensureValidationTableSeeded();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function bindTelemetry(array $payload): void
    {
        $this->app->instance(
            TelemetryCollector::class,
            new FakeTelemetryCollector($payload),
        );
    }

    protected function bindHealthyTelemetry(): void
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

    protected function bindDegradedTelemetry(): void
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

    protected function warmBaseline(): void
    {
        $this->bindHealthyTelemetry();
        $engine = app(SelfHealingPerformanceEngine::class);
        for ($i = 0; $i < 3; $i++) {
            $engine->runCycle();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function runAutonomousCycle(): array
    {
        $this->warmBaseline();
        $this->bindDegradedTelemetry();

        return app(SelfHealingPerformanceEngine::class)->runCycle();
    }

    protected function seedValidationRecommendation(float $confidence = 0.9): Recommendation
    {
        Recommendation::query()
            ->where('action_type', 'analyze')
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        $fingerprint = 'fp_ops_'.str_replace('.', '', uniqid('', true));

        QueryMetric::query()->create([
            'query_fingerprint' => $fingerprint,
            'query_label' => PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'call_count' => 100,
            'p95_ms' => 500,
            'p99_ms' => 600,
            'captured_at' => now(),
        ]);

        return Recommendation::query()->create([
            'recommendation_code' => 'REC-OPS-'.now()->format('Hisu'),
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => PostgreSqlAnalyzeEvidence::VALIDATION_SCHEMA,
            'table_name' => PostgreSqlAnalyzeEvidence::VALIDATION_TABLE,
            'what' => 'ANALYZE '.PostgreSqlAnalyzeEvidence::QUALIFIED_TARGET,
            'why' => 'Synthetic anomaly trigger — operational validation',
            'confidence' => $confidence,
            'status' => 'pending',
            'evidence' => ['query_fingerprint' => $fingerprint],
        ]);
    }

    protected function bindSuccessMetrics(): void
    {
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
            MetricSnapshotCapturer::class,
            new SequentialMetricSnapshotCapturer([$before, $before, $before, $after, $after]),
        );
    }
}
