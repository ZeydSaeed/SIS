<?php

namespace Tests\Support\Optimization;

use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use App\Optimization\SelfHealing\BaselineContextMatcher;
use App\Optimization\SelfHealing\HealthScoreEngine;
use App\Optimization\SelfHealing\MetricSnapshotCapturer;

class SequentialMetricSnapshotCapturer extends MetricSnapshotCapturer
{
    /** @var list<MetricSnapshot> */
    private array $snapshots;

    private int $index = 0;

    /**
     * @param  list<MetricSnapshot>  $snapshots
     */
    public function __construct(array $snapshots)
    {
        parent::__construct(
            new FakeTelemetryCollector([]),
            new HealthScoreEngine,
            new AdaptiveBaselineEngine(new BaselineContextMatcher),
        );
        $this->snapshots = $snapshots;
    }

    public function capture(): MetricSnapshot
    {
        $snapshot = $this->snapshots[$this->index] ?? end($this->snapshots);
        $this->index++;

        return $snapshot instanceof MetricSnapshot ? $snapshot : $this->snapshots[0];
    }
}
