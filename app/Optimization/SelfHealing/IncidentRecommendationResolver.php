<?php

namespace App\Optimization\SelfHealing;

use App\Intelligence\Expert\RuleEngine;
use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Support\CorrelationContext;
use App\Optimization\Contracts\IncidentReport;
use Illuminate\Support\Str;

final class IncidentRecommendationResolver
{
    public function __construct(
        private readonly DatabaseGuardian $guardian,
        private readonly RuleEngine $ruleEngine,
        private readonly RecommendationRanker $ranker,
        private readonly SelfHealingEventLogger $events,
    ) {}

    /**
     * @return array{matched: bool, recommendation: ?Recommendation, reason: ?string}
     */
    public function resolve(IncidentReport $incident): array
    {
        CorrelationContext::reset();
        CorrelationContext::set($incident->incidentId);

        $this->guardian->runPerformanceCycle();

        $maxTier = $this->ranker->maxAutonomousRiskTier();
        $allowedActions = config('optimization.autonomous_actions', ['analyze']);

        $candidates = Recommendation::query()
            ->where('status', 'pending')
            ->where('risk_tier', '<=', $maxTier)
            ->whereIn('action_type', $allowedActions)
            ->get();

        $ranked = $this->ranker->rank($candidates);

        foreach ($ranked as $recommendation) {
            if ($this->matchesIncident($incident, $recommendation)) {
                $this->tagRecommendation($recommendation, $incident);

                $this->events->emit('RECOMMENDATION_CREATED', [
                    'incident_id' => $incident->incidentId,
                    'recommendation_id' => $recommendation->id,
                    'recommendation_code' => $recommendation->recommendation_code,
                    'target' => $incident->target,
                    'action' => $recommendation->action_type,
                ]);

                return ['matched' => true, 'recommendation' => $recommendation->fresh(), 'reason' => null];
            }
        }

        return [
            'matched' => false,
            'recommendation' => null,
            'reason' => 'No pending recommendation matches incident target '.$incident->target,
        ];
    }

    public function assertMatches(IncidentReport $incident, Recommendation $recommendation): void
    {
        if (! $this->matchesIncident($incident, $recommendation)) {
            throw new \RuntimeException(sprintf(
                'Recommendation %s target mismatch for incident %s (expected target: %s)',
                $recommendation->recommendation_code,
                $incident->incidentId,
                $incident->target,
            ));
        }

        $storedIncidentId = $recommendation->correlation_id ?? ($recommendation->evidence['incident_id'] ?? null);
        if ($storedIncidentId !== null && $storedIncidentId !== $incident->incidentId) {
            throw new \RuntimeException('Recommendation incident_id does not match current RCA incident');
        }
    }

    private function matchesIncident(IncidentReport $incident, Recommendation $recommendation): bool
    {
        if (! in_array($recommendation->action_type, $incident->candidateActions, true)) {
            return false;
        }

        if ($incident->tableName !== null && $recommendation->table_name === $incident->tableName) {
            return true;
        }

        if ($incident->schemaName !== null
            && $recommendation->schema_name === $incident->schemaName
            && $incident->tableName !== null
            && $recommendation->table_name === $incident->tableName) {
            return true;
        }

        $evidence = $recommendation->evidence ?? [];
        if ($incident->queryFingerprint !== null
            && ($evidence['query_fingerprint'] ?? null) === $incident->queryFingerprint) {
            return true;
        }

        $target = Str::lower($incident->target);
        $table = Str::lower((string) $recommendation->table_name);
        $label = Str::lower((string) ($evidence['query_label'] ?? ''));

        if ($table !== '' && ($table === $target || str_contains($label, $target))) {
            return true;
        }

        return false;
    }

    private function tagRecommendation(Recommendation $recommendation, IncidentReport $incident): void
    {
        $evidence = array_merge($recommendation->evidence ?? [], [
            'incident_id' => $incident->incidentId,
            'target' => $incident->target,
            'root_cause' => $incident->rootCause,
        ]);

        $recommendation->update([
            'correlation_id' => $incident->incidentId,
            'evidence' => $evidence,
        ]);
    }
}
