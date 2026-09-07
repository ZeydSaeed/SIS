<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdaptiveBaselineEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['optimization.adaptive_baseline_path' => storage_path('framework/testing/adaptive-baseline.json')]);
        File::delete(config('optimization.adaptive_baseline_path'));
        config(['optimization.baseline.min_healthy_observations_to_adapt' => 3]);
    }

    #[Test]
    public function it_does_not_poison_baseline_with_degraded_observations(): void
    {
        $engine = new AdaptiveBaselineEngine;

        $engine->update(['p95_latency_ms' => 100]);
        $engine->update(['p95_latency_ms' => 500], [['metric' => 'p95_latency_ms']]);

        $baseline = $engine->load();
        $metric = $baseline['metrics']['p95_latency_ms'];

        $this->assertEquals(100, $metric['baseline_value']);
        $this->assertGreaterThan(0, $metric['degraded_observations']);
    }

    #[Test]
    public function it_adapts_baseline_after_healthy_streak(): void
    {
        $engine = new AdaptiveBaselineEngine;

        $engine->update(['p95_latency_ms' => 100]);
        $engine->update(['p95_latency_ms' => 110]);
        $engine->update(['p95_latency_ms' => 115]);
        $engine->update(['p95_latency_ms' => 120]);

        $baseline = $engine->load();
        $this->assertGreaterThanOrEqual(100, $baseline['metrics']['p95_latency_ms']['baseline_value']);
    }
}
