<?php

namespace App\Intelligence\Detection;

use App\Intelligence\Models\Detection;
use App\Intelligence\Models\QueryMetric;
use App\Intelligence\Models\TableMetric;
use App\Intelligence\Support\ConditionEvaluator;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Collection;

class ThresholdEngine
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluateTableMetrics(TableMetric $metric): Collection
    {
        $signals = [
            'table_size_gb' => $metric->tableSizeGb(),
            'table_rows' => (float) $metric->row_estimate,
            'sequential_scan_ratio' => (float) ($metric->seq_scan_ratio ?? 0),
            'growth_rate_daily_pct' => (float) ($metric->growth_rate_daily_pct ?? 0),
            'stats_age_hours' => (float) ($metric->context['stats_age_hours'] ?? 0),
        ];

        return $this->matchRules($signals, [
            'schema_name' => $metric->schema_name,
            'table_name' => $metric->table_name,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluateQueryMetric(QueryMetric $metric): Collection
    {
        $signals = [
            'query_p95_ms' => (float) $metric->p95_ms,
            'degradation_pct' => (float) ($metric->degradation_pct ?? 0),
        ];

        return $this->matchRules($signals, [
            'query_fingerprint' => $metric->query_fingerprint,
            'query_label' => $metric->query_label,
        ]);
    }

    /**
     * @param  array<string, float>  $signals
     * @param  array<string, mixed>  $context
     * @return Collection<int, array<string, mixed>>
     */
    private function matchRules(array $signals, array $context): Collection
    {
        $matches = collect();

        foreach (config('intelligence.threshold_rules', []) as $rule) {
            if (! $this->evaluator->matches($rule['condition'], $signals)) {
                continue;
            }

            $matches->push([
                'rule_id' => $rule['id'],
                'risk_tier' => $rule['risk_tier'],
                'action' => $rule['action'],
                'message' => $rule['message'],
                'signals' => $signals,
                'context' => $context,
            ]);
        }

        return $matches;
    }

    public function persistDetection(array $match, ?string $severity = 'warning'): Detection
    {
        return Detection::query()->create([
            'detection_code' => strtoupper($match['rule_id']).'-'.now()->format('YmdHis'),
            'rule_id' => $match['rule_id'],
            'risk_tier' => $match['risk_tier'],
            'severity' => $severity,
            'schema_name' => $match['context']['schema_name'] ?? null,
            'table_name' => $match['context']['table_name'] ?? null,
            'query_fingerprint' => $match['context']['query_fingerprint'] ?? null,
            'title' => $match['message'],
            'diagnosis' => $match['message'],
            'evidence' => $match['signals'],
            'degradation_score' => $match['signals']['degradation_pct'] ?? null,
            'status' => 'open',
            'correlation_id' => CorrelationContext::id(),
            'detected_at' => now(),
        ]);
    }
}
