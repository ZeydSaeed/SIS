<?php

namespace Tests\Support\Optimization;

use App\Optimization\SelfHealing\EnvironmentProfileService;
use App\Optimization\SelfHealing\TelemetryCollector;
use App\Optimization\Telemetry\ErrorRateTelemetryProvider;
use App\Optimization\Telemetry\QueueTelemetryProvider;

/**
 * Test double for TelemetryCollector.
 */
class FakeTelemetryCollector extends TelemetryCollector
{
    /** @var array<string, mixed> */
    private array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload, ?EnvironmentProfileService $environment = null)
    {
        parent::__construct(
            $environment ?? new EnvironmentProfileService,
            new ErrorRateTelemetryProvider,
            new QueueTelemetryProvider,
        );
        $this->payload = $payload;
    }

    public function collect(): array
    {
        return $this->payload;
    }
}
