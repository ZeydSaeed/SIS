<?php

namespace Tests\Feature\Optimization;

use App\Optimization\SelfHealing\EnvironmentProfileService;
use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use App\Optimization\SelfHealing\TelemetryCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\FakeTelemetryCollector;
use Tests\TestCase;

class SelfHealingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'optimization.mode' => 'observe',
            'optimization.state_path' => storage_path('framework/testing/optimization-state-pipeline'),
            'optimization.adaptive_baseline_path' => storage_path('framework/testing/adaptive-baseline-pipeline.json'),
            'optimization.environment_profile_path' => storage_path('framework/testing/environment-profile-pipeline.json'),
            'optimization.reports_path' => storage_path('framework/testing/optimization-reports'),
            'optimization.anomaly.consecutive_observations_required' => 3,
        ]);

        File::deleteDirectory(config('optimization.state_path'));
        File::delete(config('optimization.adaptive_baseline_path'));
        File::delete(config('optimization.environment_profile_path'));
        File::deleteDirectory(config('optimization.reports_path'));

        app(EnvironmentProfileService::class)->capture();
    }

    #[Test]
    public function s1_healthy_system_returns_monitor_without_optimization(): void
    {
        $this->bindTelemetry($this->healthyTelemetry());

        $engine = app(SelfHealingPerformanceEngine::class);
        $result = $engine->runCycle();

        $this->assertSame('monitor', $result['outcome']);
        $this->assertArrayNotHasKey('incident_id', $result);
    }

    #[Test]
    public function s2_temporary_spike_does_not_trigger_anomaly_due_to_hysteresis(): void
    {
        $this->bindTelemetry([
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => 500,
            'cpu_pct' => 95,
            'memory_mb' => 128,
            'context_fingerprint' => ['environment' => 'testing'],
        ]);

        $engine = app(SelfHealingPerformanceEngine::class);

        $first = $engine->runCycle();
        $second = $engine->runCycle();

        $this->assertSame('monitor', $first['outcome']);
        $this->assertSame('monitor', $second['outcome']);
        $this->assertSame(0, $first['anomalies'] ?? 0);
    }

    #[Test]
    public function s9_concurrent_cycle_is_skipped_when_worker_lock_held(): void
    {
        $this->bindTelemetry($this->healthyTelemetry());

        $lock = Cache::lock('optimization:self-healing:worker', 60);
        $lock->get();

        try {
            $engine = app(SelfHealingPerformanceEngine::class);
            $result = $engine->runCycle();

            $this->assertTrue($result['skipped'] ?? false);
            $this->assertSame('another cycle in progress', $result['reason']);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function s10_observe_mode_never_attempts_self_heal(): void
    {
        config(['optimization.mode' => 'observe']);

        $this->bindTelemetry([
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => 800,
            'cpu_pct' => 90,
            'memory_mb' => 256,
            'db_queries_per_request' => 50,
            'cache_hit_ratio' => 0.3,
            'context_fingerprint' => ['environment' => 'testing'],
        ]);

        $engine = app(SelfHealingPerformanceEngine::class);

        for ($i = 0; $i < 5; $i++) {
            $result = $engine->runCycle();
        }

        $this->assertNotSame('heal_applied', $result['outcome'] ?? null);
        $this->assertNotSame('autonomous', config('optimization.mode'));
    }

    #[Test]
    public function optimization_run_autonomous_command_is_disabled(): void
    {
        $this->artisan('optimization:run')
            ->assertSuccessful()
            ->expectsOutputToContain('incident-driven');
    }

    #[Test]
    public function optimization_status_command_runs(): void
    {
        $this->bindTelemetry($this->healthyTelemetry());

        $this->artisan('optimization:status')
            ->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindTelemetry(array $payload): void
    {
        $this->app->instance(TelemetryCollector::class, new FakeTelemetryCollector($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function healthyTelemetry(): array
    {
        return [
            'collected_at' => now()->toIso8601String(),
            'p95_latency_ms' => 80,
            'p99_latency_ms' => 120,
            'cpu_pct' => 20,
            'memory_mb' => 64,
            'memory_pct' => 10,
            'db_queries_per_request' => 3,
            'cache_hit_ratio' => 0.95,
            'error_rate_pct' => null,
            'context_fingerprint' => ['environment' => 'testing'],
        ];
    }
}
