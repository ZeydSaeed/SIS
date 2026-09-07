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
            ['p95_latency_ms' => 200, 'error_rate_pct' => 0.1],
            ['p95_latency_ms' => 150, 'error_rate_pct' => 0.1],
        );

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['regressions']);
    }

    #[Test]
    public function it_detects_latency_regression(): void
    {
        $guard = new CrossMetricGuard;

        $result = $guard->evaluate(
            ['p95_latency_ms' => 200],
            ['p95_latency_ms' => 300],
        );

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['regressions']);
    }
}
