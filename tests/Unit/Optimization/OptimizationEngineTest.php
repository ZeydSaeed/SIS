<?php

namespace Tests\Unit\Optimization;

use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\OptimizationEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OptimizationEngineTest extends TestCase
{
    #[Test]
    public function run_autonomous_is_disabled(): void
    {
        $engine = app(OptimizationEngine::class);
        $result = $engine->runAutonomous();

        $this->assertFalse($result['executed']);
        $this->assertStringContainsString('incident-driven', $result['reason']);
    }

    #[Test]
    public function run_for_incident_requires_autonomous_mode(): void
    {
        config(['optimization.mode' => 'observe']);

        $engine = app(OptimizationEngine::class);
        $incident = new IncidentReport(
            incidentId: 'INC-001',
            timestamp: now()->toIso8601String(),
            primaryComponent: 'database',
            affectedComponent: 'students',
            target: 'students',
            rootCause: 'test',
            confidence: 0.9,
            evidence: [],
            supportingMetrics: [],
            candidateActions: ['analyze'],
            risk: 'low',
            severity: 'P1',
        );

        $result = $engine->runForIncident($incident);

        $this->assertFalse($result['executed']);
        $this->assertStringContainsString('Autonomous mode disabled', $result['reason']);
    }

    #[Test]
    public function default_mode_is_observe(): void
    {
        $engine = app(OptimizationEngine::class);
        $this->assertSame(OptimizationMode::Observe, $engine->mode());
    }
}
