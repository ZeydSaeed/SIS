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
     * @param  array<string, mixed>  $metrics  HTTP workload snapshot metrics
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluateHttpWorkload(array $metrics): Collection
    {
        $signals = [
            'http_p95_ms' => (float) ($metrics['p95_ms'] ?? 0),
            'budget_exceeded' => ($metrics['budget_exceeded'] ?? false) === true,
            'error_rate_pct' => (float) ($metrics['error_rate_pct'] ?? 0),
            'mean_db_queries_per_request' => (float) ($metrics['mean_db_queries_per_request'] ?? 0),
            'sample_count' => (float) ($metrics['sample_count'] ?? 0),
        ];

        return $this->matchRules($signals, [
            'workload' => $metrics['workload'] ?? null,
            'routes' => $metrics['routes'] ?? [],
            'performance_budget_p95_ms' => $metrics['performance_budget_p95_ms'] ?? null,
            'source' => $metrics['source'] ?? 'http_request_telemetry',
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
            'evidence' => array_merge($match['signals'], array_filter($match['context'] ?? [], fn ($value) => $value !== null && $value !== [])),
            'degradation_score' => $this->degradationScore($match),
            'status' => 'open',
            'correlation_id' => CorrelationContext::id(),
            'detected_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function degradationScore(array $match): ?float
    {
        if (isset($match['signals']['degradation_pct'])) {
            return (float) $match['signals']['degradation_pct'];
        }

        if (($match['signals']['budget_exceeded'] ?? false) !== true) {
            return null;
        }

        $p95 = (float) ($match['signals']['http_p95_ms'] ?? 0);
        $budget = (float) ($match['context']['performance_budget_p95_ms'] ?? 0);

        if ($budget <= 0) {
            return null;
        }

        return round((($p95 - $budget) / $budget) * 100, 2);
    }
}
