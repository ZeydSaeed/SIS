<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\Execution\AnalyzeTargetPolicy;

/**
 * Unified fail-closed policy for controlled autonomous ANALYZE.
 */
final class AutonomousExecutionPolicy
{
    public function __construct(
        private readonly AnalyzeTargetPolicy $targetPolicy,
        private readonly AutonomousKillSwitch $killSwitch,
        private readonly AutonomousRateLimiter $rateLimiter,
    ) {}

    /**
     * @return array{allowed: bool, code: string, reason: ?string, checks: array<string, string>}
     */
    public function evaluate(
        IncidentReport $incident,
        OptimizationMode $effectiveMode,
        bool $circuitOpen,
        bool $baselineStale,
        bool $targetLocked,
        bool $onCooldown,
        bool $scaleBlocked,
        bool $checkpointBlocks,
        MetricSnapshot $metrics,
        string $normalizedTarget,
        string $cycleId,
    ): array {
        $checks = [];

        if ($this->killSwitch->isEngaged()) {
            return $this->deny('kill_switch_engaged', 'Autonomous kill switch is engaged', $checks);
        }
        $checks['kill_switch'] = 'pass';

        if ($effectiveMode !== OptimizationMode::Autonomous) {
            return $this->deny('mode_not_autonomous', 'Optimization mode is not autonomous', $checks);
        }
        $checks['mode'] = 'pass';

        if (! $this->isControlledEnvironment()) {
            return $this->deny('environment_unauthorized', 'Environment not authorized for autonomous ANALYZE', $checks);
        }
        $checks['environment'] = 'pass';

        $rateLimit = $this->rateLimiter->evaluate($normalizedTarget, $cycleId);
        if (! $rateLimit['allowed']) {
            return $this->deny($rateLimit['code'], $rateLimit['reason'] ?? 'Rate limit exceeded', $checks);
        }
        $checks['rate_limit'] = 'pass';

        if ($incident->candidateActions === [] || ! in_array('analyze', $incident->candidateActions, true)) {
            return $this->deny('operation_not_allowed', 'ANALYZE is not an approved candidate action', $checks);
        }
        $checks['operation'] = 'pass';

        if (! $this->allowlistConfigurationValid()) {
            return $this->deny('allowlist_invalid', 'ANALYZE allowlist is missing, empty, or contains wildcards', $checks);
        }
        $checks['allowlist_config'] = 'pass';

        if ($incident->schemaName === null || $incident->tableName === null) {
            return $this->deny('target_missing', 'Incident target schema/table missing', $checks);
        }

        if (! $this->targetPolicy->isAllowlisted($incident->schemaName, $incident->tableName)) {
            return $this->deny('target_not_allowlisted', 'Target not on explicit ANALYZE allowlist', $checks);
        }
        $checks['target_allowlist'] = 'pass';

        $minConfidence = (float) config('optimization.scoring.min_confidence_for_autonomous', 0.75);
        if ($incident->confidence < $minConfidence) {
            return $this->deny('insufficient_confidence', "Root cause confidence below {$minConfidence}", $checks);
        }
        $checks['confidence'] = 'pass';

        if ($circuitOpen) {
            return $this->deny('circuit_breaker_open', 'Circuit breaker is open', $checks);
        }
        $checks['circuit_breaker'] = 'pass';

        if ($onCooldown) {
            return $this->deny('cooldown_active', 'Target is on optimization cooldown', $checks);
        }
        $checks['cooldown'] = 'pass';

        if ($targetLocked) {
            return $this->deny('target_locked', 'Target lock is held by another operation', $checks);
        }
        $checks['target_lock'] = 'pass';

        if ($baselineStale) {
            return $this->deny('baseline_incompatible', 'Baseline is stale or context-incompatible', $checks);
        }
        $checks['baseline'] = 'pass';

        if ($scaleBlocked) {
            return $this->deny('data_scale_blocked', 'Data scale context blocks autonomous optimization', $checks);
        }
        $checks['data_scale'] = 'pass';

        if ($checkpointBlocks) {
            return $this->deny('checkpoint_ambiguous', 'Ambiguous or recovered checkpoint blocks retry', $checks);
        }
        $checks['checkpoint'] = 'pass';

        $unknownCritical = $metrics->unavailableCriticalMetrics();
        if ($unknownCritical !== [] && config('optimization.gates.block_autonomous_on_unknown_critical', true)) {
            return $this->deny(
                'critical_telemetry_unknown',
                'Critical metrics unavailable: '.implode(', ', $unknownCritical),
                $checks,
            );
        }
        $checks['telemetry'] = 'pass';

        return [
            'allowed' => true,
            'code' => 'eligible',
            'reason' => null,
            'checks' => $checks,
        ];
    }

    public function isControlledEnvironment(): bool
    {
        if (! config('optimization.autonomous.require_controlled_environment', true)) {
            return true;
        }

        $allowed = config('optimization.autonomous.controlled_environments', []);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array((string) config('app.env'), $allowed, true);
    }

    public function allowlistConfigurationValid(): bool
    {
        $allowed = config('optimization.analyze.allowed_targets', []);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        foreach ($allowed as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '' || str_contains($entry, '*') || str_contains($entry, ';') || str_contains($entry, '--')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $checks
     * @return array{allowed: bool, code: string, reason: ?string, checks: array<string, string>}
     */
    private function deny(string $code, string $reason, array $checks): array
    {
        return [
            'allowed' => false,
            'code' => $code,
            'reason' => $reason,
            'checks' => $checks,
        ];
    }
}
