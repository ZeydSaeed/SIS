<?php

namespace App\Intelligence\Learning;

use App\Intelligence\Enums\OptimizationOutcome;
use App\Intelligence\Models\OptimizationEvent;
use Illuminate\Support\Collection;

class ConfidenceEngine
{
    public function calculateForRule(string $ruleId, float $contextSimilarity = 1.0): float
    {
        $events = OptimizationEvent::query()
            ->where('rule_id', $ruleId)
            ->whereIn('outcome', [
                OptimizationOutcome::Success->value,
                OptimizationOutcome::Failure->value,
                OptimizationOutcome::RolledBack->value,
            ])
            ->get();

        if ($events->isEmpty()) {
            return 0.5;
        }

        $weightedSuccess = $this->weightedSuccessRate($events);
        $sampleFactor = $this->sampleFactor($events->count());

        return round(min(1.0, $weightedSuccess * $contextSimilarity * $sampleFactor), 4);
    }

    /**
     * @param  Collection<int, OptimizationEvent>  $events
     */
    private function weightedSuccessRate(Collection $events): float
    {
        $weightSum = 0.0;
        $successSum = 0.0;

        foreach ($events as $event) {
            $weight = (float) ($event->recency_weight ?: $this->recencyWeight($event->executed_at));
            $success = $event->outcome === OptimizationOutcome::Success->value ? 1.0 : 0.0;
            $weightSum += $weight;
            $successSum += $success * $weight;
        }

        return $weightSum > 0 ? $successSum / $weightSum : 0.5;
    }

    private function sampleFactor(int $count): float
    {
        if ($count < 10) {
            return 0.5;
        }

        if ($count < 30) {
            return 0.7;
        }

        return min(1.0, $count / 50);
    }

    private function recencyWeight(?\DateTimeInterface $executedAt): float
    {
        if ($executedAt === null) {
            return 1.0;
        }

        $months = now()->diffInMonths($executedAt);

        foreach (config('intelligence.learning.recency_weights', []) as $band) {
            if ($band['max_age_months'] === null || $months <= $band['max_age_months']) {
                return (float) $band['weight'];
            }
        }

        return 0.4;
    }
}
