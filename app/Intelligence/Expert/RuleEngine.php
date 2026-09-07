<?php

namespace App\Intelligence\Expert;

use App\Intelligence\Models\Detection;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Simulation\CostEstimator;
use App\Intelligence\Support\ConditionEvaluator;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RuleEngine
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
        private readonly CostEstimator $costEstimator,
    ) {}

    /**
     * @param  array<string, float|bool>  $signals
     * @return Collection<int, array<string, mixed>>
     */
    public function diagnose(array $signals, array $context = []): Collection
    {
        $diagnoses = collect();

        foreach (config('intelligence.expert_rules', []) as $rule) {
            if (! $this->evaluator->matches($rule['when'], $signals)) {
                continue;
            }

            $diagnoses->push([
                'rule_id' => $rule['id'],
                'risk_tier' => $rule['risk_tier'],
                'diagnosis' => $rule['diagnosis'],
                'recommendation_type' => $rule['recommendation_type'],
                'alternatives' => $rule['alternatives'] ?? [],
                'context' => $context,
                'signals' => $signals,
            ]);
        }

        return $diagnoses;
    }

    public function createRecommendation(Detection $detection, array $diagnosis, float $confidence = 0.75): Recommendation
    {
        $code = 'REC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $costAnalysis = $this->buildCostAnalysis($diagnosis);

        return Recommendation::query()->create([
            'recommendation_code' => $code,
            'detection_id' => $detection->id,
            'rule_id' => $diagnosis['rule_id'],
            'risk_tier' => $diagnosis['risk_tier'],
            'action_type' => $diagnosis['recommendation_type'],
            'schema_name' => $detection->schema_name,
            'table_name' => $detection->table_name,
            'what' => $this->buildWhat($diagnosis),
            'why' => $diagnosis['diagnosis'],
            'evidence' => $detection->evidence,
            'alternatives' => collect($diagnosis['alternatives'])->map(fn ($alt) => [
                'option' => $alt,
                'status' => 'candidate',
            ])->all(),
            'expected_impact' => [
                'expected_improvement_pct' => $this->expectedImprovement($diagnosis['recommendation_type']),
            ],
            'cost_analysis' => $costAnalysis,
            'rollback_plan' => $this->rollbackPlan($diagnosis),
            'confidence' => $confidence,
            'context_similarity' => null,
            'blast_radius_score' => $this->blastRadiusScore($diagnosis),
            'status' => 'pending',
            'correlation_id' => CorrelationContext::id(),
        ]);
    }

    private function buildWhat(array $diagnosis): string
    {
        return match ($diagnosis['recommendation_type']) {
            'analyze' => 'Run ANALYZE on affected table to refresh planner statistics',
            'index_add' => 'Evaluate B-Tree index for dominant filter/join columns',
            'partition_review' => 'Review partition strategy — simulation and ADR required',
            'schema_review' => 'Review schema normalization — migration proposal required',
            'add_foreign_key' => 'Add missing foreign key with restrictOnDelete',
            default => 'Review expert recommendation '.$diagnosis['rule_id'],
        };
    }

    private function expectedImprovement(string $type): int
    {
        return match ($type) {
            'analyze' => 15,
            'index_add' => 65,
            'partition_review' => 70,
            default => 20,
        };
    }

    private function rollbackPlan(array $diagnosis): ?string
    {
        return match ($diagnosis['recommendation_type']) {
            'analyze' => 'No rollback required — statistics refresh only',
            'index_add' => 'DROP INDEX CONCURRENTLY if regression detected',
            default => 'Follow DATABASE-INTELLIGENCE-SAFETY.md rollback checklist',
        };
    }

    private function blastRadiusScore(array $diagnosis): int
    {
        return match ($diagnosis['recommendation_type']) {
            'analyze' => 10,
            'index_add' => 25,
            'partition_review' => 75,
            'schema_review' => 90,
            default => 40,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCostAnalysis(array $diagnosis): ?array
    {
        $options = collect($diagnosis['alternatives'] ?? [])
            ->map(fn (string $alt) => [
                'option' => $alt,
                'performance_score' => $this->expectedImprovement($diagnosis['recommendation_type']),
                'storage_cost_score' => match ($diagnosis['recommendation_type']) {
                    'index_add' => 40,
                    'partition_review' => 60,
                    default => 20,
                },
                'maintenance_cost_score' => match ($diagnosis['recommendation_type']) {
                    'analyze' => 10,
                    'index_add' => 35,
                    default => 50,
                },
                'complexity_score' => $this->blastRadiusScore($diagnosis),
                'risk_score' => (int) $diagnosis['risk_tier'] * 25,
                'expected_improvement_pct' => $this->expectedImprovement($diagnosis['recommendation_type']),
                'write_overhead_pct' => match ($diagnosis['recommendation_type']) {
                    'index_add' => 15,
                    default => 5,
                },
            ])
            ->all();

        if ($options === []) {
            return null;
        }

        $ranked = $this->costEstimator->rankOptions($options);
        $best = $ranked[0] ?? null;

        return [
            'ranked_options' => $ranked,
            'recommended_option' => $best['option'] ?? null,
            'should_reject' => $best !== null && $this->costEstimator->shouldReject($best),
        ];
    }
}
