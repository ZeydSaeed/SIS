<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\SelfHealing\AutonomousExecutionPolicy;
use App\Optimization\SelfHealing\AutonomousKillSwitch;
use App\Optimization\SelfHealing\AutonomousRateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AutonomousExecutionPolicyTest extends TestCase
{
    private AutonomousExecutionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'testing',
            'optimization.autonomous.require_controlled_environment' => true,
            'optimization.autonomous.controlled_environments' => ['testing'],
            'optimization.autonomous.kill_switch' => false,
            'optimization.analyze.allowed_targets' => ['intelligence.optimization_validation_target'],
            'optimization.scoring.min_confidence_for_autonomous' => 0.75,
        ]);

        $this->policy = app(AutonomousExecutionPolicy::class);
    }

    #[Test]
    public function eligible_when_all_gates_pass(): void
    {
        $result = $this->evaluatePolicy();

        $this->assertTrue($result['allowed']);
        $this->assertSame('eligible', $result['code']);
    }

    #[Test]
    public function blocks_when_environment_not_controlled(): void
    {
        config(['app.env' => 'production']);

        $result = $this->evaluatePolicy();

        $this->assertFalse($result['allowed']);
        $this->assertSame('environment_unauthorized', $result['code']);
    }

    #[Test]
    public function wildcard_allowlist_is_invalid(): void
    {
        config(['optimization.analyze.allowed_targets' => ['*']]);
        $this->assertFalse($this->policy->allowlistConfigurationValid());
    }

    #[Test]
    public function kill_switch_blocks_even_when_other_gates_pass(): void
    {
        config(['optimization.autonomous.kill_switch' => true]);

        $result = $this->evaluatePolicy();

        $this->assertFalse($result['allowed']);
        $this->assertSame('kill_switch_engaged', $result['code']);
    }

    #[Test]
    public function invalid_kill_switch_value_fails_closed(): void
    {
        config(['optimization.autonomous.kill_switch' => 'maybe']);

        $this->assertTrue(app(AutonomousKillSwitch::class)->isEngaged());
    }

    #[Test]
    public function rate_limit_blocks_when_target_window_exceeded(): void
    {
        config([
            'optimization.autonomous.rate_limits.max_per_target_per_window' => 1,
            'optimization.state_path' => storage_path('framework/testing/rate-limit-policy-state'),
        ]);
        \Illuminate\Support\Facades\File::deleteDirectory(config('optimization.state_path'));

        app(AutonomousRateLimiter::class)->recordExecution(
            'intelligence.optimization_validation_target',
            'CYC-PRIOR',
        );

        $result = $this->evaluatePolicy();

        $this->assertFalse($result['allowed']);
        $this->assertSame('rate_limit_target', $result['code']);
    }

    /**
     * @return array{allowed: bool, code: string, reason: ?string, checks: array<string, string>}
     */
    private function evaluatePolicy(): array
    {
        return $this->policy->evaluate(
            incident: $this->incident(),
            effectiveMode: OptimizationMode::Autonomous,
            circuitOpen: false,
            baselineStale: false,
            targetLocked: false,
            onCooldown: false,
            scaleBlocked: false,
            checkpointBlocks: false,
            metrics: $this->metrics(),
            normalizedTarget: 'intelligence.optimization_validation_target',
            cycleId: 'CYC-UNIT-001',
        );
    }

    private function incident(): IncidentReport
    {
        return new IncidentReport(
            incidentId: 'INC-UNIT',
            timestamp: now()->toIso8601String(),
            primaryComponent: 'database',
            affectedComponent: 'database',
            target: 'intelligence.optimization_validation_target',
            rootCause: 'stale_statistics',
            confidence: 0.9,
            evidence: [],
            supportingMetrics: [],
            candidateActions: ['analyze'],
            risk: 'low',
            severity: 'medium',
            schemaName: 'intelligence',
            tableName: 'optimization_validation_target',
        );
    }

    private function metrics(): MetricSnapshot
    {
        return new MetricSnapshot(
            capturedAt: now()->toIso8601String(),
            p95LatencyMs: 100.0,
            cacheHitRatio: 0.9,
        );
    }
}
