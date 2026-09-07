<?php

namespace Tests\Unit\Optimization;

use App\Optimization\Gates\CrossMetricGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrossMetricGuardTest extends TestCase
{
    #[Test]
    public function it_passes_when_metrics_improve_or_stable(): void
    {
        $guard = new CrossMetricGuard;

        $result = $guard->evaluate(
            [
                'p95_latency_ms' => 200,
                'error_rate_pct' => 0.1,
                'cache_hit_ratio' => 0.9,
                'cpu_pct' => 30,
                'memory_mb' => 128,
            ],
            [
                'p95_latency_ms' => 150,
                'error_rate_pct' => 0.1,
                'cache_hit_ratio' => 0.9,
                'cpu_pct' => 30,
                'memory_mb' => 128,
            ],
        );

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['regressions']);
    }

    #[Test]
    public function it_detects_latency_regression(): void
    {
        $guard = new CrossMetricGuard;

        $result = $guard->evaluate(
            ['p95_latency_ms' => 200, 'cache_hit_ratio' => 0.9],
            ['p95_latency_ms' => 300, 'cache_hit_ratio' => 0.9],
        );

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['regressions']);
    }

    #[Test]
    public function it_blocks_autonomous_accept_when_critical_metrics_unknown(): void
    {
        $guard = new CrossMetricGuard;

        $result = $guard->evaluate(
            ['p95_latency_ms' => null, 'cache_hit_ratio' => null],
            ['p95_latency_ms' => 150, 'cache_hit_ratio' => 0.9],
        );

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['unknown_critical']);
    }
}
