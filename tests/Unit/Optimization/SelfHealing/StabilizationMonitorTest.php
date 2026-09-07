<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\SelfHealingStateStore;
use App\Optimization\SelfHealing\StabilizationMonitor;
use App\Optimization\SelfHealing\TelemetryCollector;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Optimization\FakeTelemetryCollector;
use Tests\TestCase;

class StabilizationMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'optimization.state_path' => storage_path('framework/testing/optimization-state-stab'),
            'optimization.stabilization.min_observations' => 1,
            'optimization.stabilization.window_minutes' => 0,
        ]);
        File::deleteDirectory(config('optimization.state_path'));
    }

    #[Test]
    public function s7_stabilization_regression_triggers_rollback(): void
    {
        $store = new SelfHealingStateStore;
        $store->mutate(function (array $state) {
            $state['active_stabilizations']['OPT-001'] = [
                'started_at' => now()->subMinutes(5)->toIso8601String(),
                'observations' => 0,
                'target' => 'public.students',
                'before_metrics' => [
                    'p95_latency_ms' => 100,
                    'cache_hit_ratio' => 0.9,
                ],
            ];

            return $state;
        });

        $this->app->instance(TelemetryCollector::class, new FakeTelemetryCollector([
            'p95_latency_ms' => 250,
            'cache_hit_ratio' => 0.9,
        ]));

        $monitor = app(StabilizationMonitor::class);
        $results = $monitor->checkPending();

        $this->assertCount(1, $results);
        $this->assertSame('rollback', $results[0]['outcome']);
        $this->assertStringContainsString('cross-metric', $results[0]['reason']);
    }
}
