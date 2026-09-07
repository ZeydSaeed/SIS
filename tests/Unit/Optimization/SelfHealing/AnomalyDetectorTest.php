<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use App\Optimization\SelfHealing\AnomalyDetector;
use App\Optimization\SelfHealing\SelfHealingStateStore;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnomalyDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'optimization.state_path' => storage_path('framework/testing/optimization-state-hysteresis'),
            'optimization.anomaly.consecutive_observations_required' => 3,
        ]);
        File::deleteDirectory(config('optimization.state_path'));
    }

    #[Test]
    public function it_requires_hysteresis_before_reporting_anomaly(): void
    {
        $detector = new AnomalyDetector(new AdaptiveBaselineEngine, new SelfHealingStateStore);

        $telemetry = ['p95_latency_ms' => 300];
        $health = [
            'dimensions' => [
                'p95_latency_ms' => [
                    'status' => 'degraded',
                    'degradation_pct' => 50,
                    'current' => 300,
                    'baseline' => 200,
                ],
            ],
        ];

        $first = $detector->detect($telemetry, $health);
        $second = $detector->detect($telemetry, $health);
        $third = $detector->detect($telemetry, $health);

        $this->assertSame([], $first);
        $this->assertSame([], $second);
        $this->assertCount(1, $third);
    }
}
