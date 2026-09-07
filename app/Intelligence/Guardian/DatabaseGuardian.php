<?php

namespace App\Intelligence\Guardian;

use App\Intelligence\Detection\ThresholdEngine;
use App\Intelligence\Expert\RuleEngine;
use App\Intelligence\Learning\ConfidenceEngine;
use App\Intelligence\Monitoring\DatabaseMonitor;
use App\Intelligence\Monitoring\PgStatStatementsCollector;
use App\Intelligence\Monitoring\QueryMonitor;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use App\Intelligence\Optimization\SafeAutoExecutor;
use App\Intelligence\SelfHealing\SelfHealingEngine;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Collection;

class DatabaseGuardian
{
    public function __construct(
        private readonly DatabaseMonitor $databaseMonitor,
        private readonly QueryMonitor $queryMonitor,
        private readonly HttpRequestTelemetryMonitor $httpRequestTelemetryMonitor,
        private readonly PgStatStatementsCollector $pgStatStatementsCollector,
        private readonly ThresholdEngine $thresholdEngine,
        private readonly RuleEngine $ruleEngine,
        private readonly ConfidenceEngine $confidenceEngine,
        private readonly SafeAutoExecutor $safeAutoExecutor,
        private readonly SelfHealingEngine $selfHealingEngine,
        private readonly SchemaGuardian $schemaGuardian,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function runHealthCycle(): array
    {
        CorrelationContext::reset();

        $snapshot = $this->databaseMonitor->collectHealthSnapshot();
        $selfHealing = $this->selfHealingEngine->evaluate($snapshot);

        return [
            'snapshot_id' => $snapshot->id,
            'self_healing_action_id' => $selfHealing?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runPerformanceCycle(): array
    {
        CorrelationContext::reset();

        $httpSnapshots = $this->httpRequestTelemetryMonitor->flush();
        $queryMetrics = $this->queryMonitor->flushRuntimeSamples();
        $pgStatMetrics = $this->pgStatStatementsCollector->collect();
        $allMetrics = $queryMetrics->merge($pgStatMetrics);

        $detections = collect();
        $recommendations = collect();
        $executions = collect();

        foreach ($httpSnapshots as $snapshot) {
            $metrics = $snapshot->metrics ?? [];
            if (! is_array($metrics)) {
                continue;
            }

            foreach ($this->thresholdEngine->evaluateHttpWorkload($metrics) as $match) {
                $detection = $this->thresholdEngine->persistDetection($match, 'warning');
                $detections->push($detection);

                foreach ($this->ruleEngine->diagnose($match['signals'], $match['context']) as $diagnosis) {
                    $confidence = $this->confidenceEngine->calculateForRule($diagnosis['rule_id']);
                    $recommendation = $this->ruleEngine->createRecommendation($detection, $diagnosis, $confidence);
                    $recommendations->push($recommendation);
                }
            }
        }

        foreach ($allMetrics as $metric) {
            foreach ($this->thresholdEngine->evaluateQueryMetric($metric) as $match) {
                $detection = $this->thresholdEngine->persistDetection($match, 'warning');
                $detections->push($detection);

                $signals = array_merge($match['signals'], [
                    'query_p95_ms' => (float) $metric->p95_ms,
                    'degradation_pct' => (float) ($metric->degradation_pct ?? 0),
                ]);

                foreach ($this->ruleEngine->diagnose($signals, [
                    'query_fingerprint' => $metric->query_fingerprint,
                    'query_label' => $metric->query_label,
                ]) as $diagnosis) {
                    $confidence = $this->confidenceEngine->calculateForRule($diagnosis['rule_id']);
                    $recommendation = $this->ruleEngine->createRecommendation($detection, $diagnosis, $confidence);
                    $recommendations->push($recommendation);
                }
            }
        }

        return [
            'http_workload_snapshots' => $httpSnapshots->count(),
            'query_metrics' => $allMetrics->count(),
            'pg_stat_metrics' => $pgStatMetrics->count(),
            'detections' => $detections->count(),
            'recommendations' => $recommendations->count(),
            'executions' => $executions->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runGrowthAndOptimizationCycle(): array
    {
        CorrelationContext::reset();

        $tableMetrics = $this->databaseMonitor->collectTableMetrics();
        $detections = collect();
        $recommendations = collect();
        $executions = collect();

        foreach ($tableMetrics as $metric) {
            $thresholdMatches = $this->thresholdEngine->evaluateTableMetrics($metric);

            foreach ($thresholdMatches as $match) {
                $detection = $this->thresholdEngine->persistDetection($match);
                $detections->push($detection);

                $signals = array_merge($match['signals'], [
                    'table_size_gb' => $metric->tableSizeGb(),
                    'table_rows' => (float) $metric->row_estimate,
                    'sequential_scan_ratio' => (float) ($metric->seq_scan_ratio ?? 0),
                    'stats_age_hours' => (float) ($metric->context['stats_age_hours'] ?? 0),
                ]);

                foreach ($this->ruleEngine->diagnose($signals, [
                    'schema_name' => $metric->schema_name,
                    'table_name' => $metric->table_name,
                ]) as $diagnosis) {
                    $confidence = $this->confidenceEngine->calculateForRule($diagnosis['rule_id']);
                    $recommendation = $this->ruleEngine->createRecommendation($detection, $diagnosis, $confidence);
                    $recommendations->push($recommendation);

                    if (! config('optimization.unified_safety_pipeline', true)) {
                        $event = $this->safeAutoExecutor->attempt($recommendation);
                        if ($event !== null) {
                            $executions->push($event);
                        }
                    }
                }
            }
        }

        return [
            'table_metrics' => $tableMetrics->count(),
            'detections' => $detections->count(),
            'recommendations' => $recommendations->count(),
            'executions' => $executions->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function runSchemaValidation(): Collection
    {
        return $this->schemaGuardian->validate();
    }
}
