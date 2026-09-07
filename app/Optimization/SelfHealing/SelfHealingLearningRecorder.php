<?php

namespace App\Optimization\SelfHealing;

use App\Intelligence\Learning\ConfidenceEngine;
use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Contracts\MetricSnapshot;
use App\Optimization\Memory\OptimizationHistoryRecorder;

/**
 * Records optimization outcomes for future confidence calibration.
 * NEVER escalates tiers — learning informs confidence only.
 */
final class SelfHealingLearningRecorder
{
    public function __construct(
        private readonly OptimizationHistoryRecorder $history,
        private readonly ConfidenceEngine $confidenceEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public function record(
        IncidentReport $incident,
        MetricSnapshot $before,
        ?MetricSnapshot $after,
        array $result,
        string $decision,
    ): string {
        $ruleId = $result['checkpoint']['rule_id'] ?? ($result['rule_id'] ?? 'unknown');
        $learnedConfidence = $this->confidenceEngine->calculateForRule((string) $ruleId);

        return $this->history->record([
            'incident_id' => $incident->incidentId,
            'target' => $incident->target,
            'root_cause' => $incident->rootCause,
            'confidence_before' => $incident->confidence,
            'confidence_learned' => $learnedConfidence,
            'expected_impact' => $incident->evidence,
            'actual_impact' => [
                'before' => $before->toArray(),
                'after' => $after?->toArray(),
            ],
            'decision' => $decision,
            'rollback' => ($result['decision'] ?? '') === 'REJECTED' || ($result['rollback'] ?? false),
            'stabilization_result' => $result['stabilization'] ?? null,
            'environment' => $before->context,
            'result' => $result,
        ]);
    }
}
