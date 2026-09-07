<?php

namespace Tests\Support\Optimization;

use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use App\Optimization\SelfHealing\BaselineContextMatcher;
use App\Optimization\SelfHealing\HealthScoreEngine;
use App\Optimization\SelfHealing\MetricSnapshotCapturer;

class FakeMetricSnapshotCapturer extends MetricSnapshotCapturer
{
    public function __construct(private readonly MetricSnapshot $snapshot)
    {
        parent::__construct(
            new FakeTelemetryCollector([]),
            new HealthScoreEngine,
            new AdaptiveBaselineEngine(new BaselineContextMatcher),
        );
    }

    public function capture(): MetricSnapshot
    {
        return $this->snapshot;
    }
}
