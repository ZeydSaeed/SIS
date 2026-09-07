<?php

namespace Tests\Unit\Optimization\SelfHealing;

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
            'optimization.anomaly.sustained_degradation_minutes' => 0,
        ]);
        File::deleteDirectory(config('optimization.state_path'));
    }

    #[Test]
    public function it_requires_hysteresis_before_reporting_anomaly(): void
    {
        $detector = new AnomalyDetector(new SelfHealingStateStore);

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

    #[Test]
    public function it_requires_sustained_degradation_minutes_when_configured(): void
    {
        config(['optimization.anomaly.sustained_degradation_minutes' => 15]);

        $store = new SelfHealingStateStore;
        $store->mutate(function (array $state) {
            $state['degradation_streaks'] = ['p95_latency_ms' => 5];
            $state['degradation_started_at'] = ['p95_latency_ms' => now()->subMinutes(2)->toIso8601String()];

            return $state;
        });

        $detector = new AnomalyDetector($store);
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

        $this->assertSame([], $detector->detect(['p95_latency_ms' => 300], $health));
    }
}
