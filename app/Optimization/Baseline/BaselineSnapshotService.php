<?php

namespace App\Optimization\Baseline;

use App\Intelligence\Models\BaselineSnapshot;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\QueryMetric;
use App\Intelligence\Monitoring\DatabaseMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BaselineSnapshotService
{
    public function __construct(
        private readonly DatabaseMonitor $databaseMonitor,
    ) {}

    public function capture(string $label = 'scheduled'): BaselineSnapshot
    {
        $tableMetrics = $this->databaseMonitor->collectTableMetrics();

        $tableSizes = $tableMetrics->mapWithKeys(fn ($m) => [
            "{$m->schema_name}.{$m->table_name}" => [
                'size_bytes' => (int) $m->size_bytes,
                'row_estimate' => (int) $m->row_estimate,
            ],
        ])->all();

        $queryBaselines = QueryMetric::query()
            ->where('captured_at', '>=', now()->subDays(7))
            ->orderByDesc('captured_at')
            ->limit(100)
            ->get()
            ->groupBy('query_fingerprint')
            ->map(fn (Collection $group) => [
                'p95_ms' => (float) $group->avg('p95_ms'),
                'call_count' => (int) $group->sum('call_count'),
                'query_label' => $group->first()?->query_label,
            ])
            ->all();

        $lastHealth = MonitoringSnapshot::query()->orderByDesc('captured_at')->first();

        return BaselineSnapshot::query()->create([
            'baseline_code' => 'BL-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'label' => $label,
            'context_fingerprint' => [
                'app_version' => config('app.version', 'dev'),
                'performance_budget' => config('intelligence.performance_budget_version'),
                'knowledge_version' => config('intelligence.knowledge_version'),
                'optimization_mode' => config('optimization.mode'),
                'database_size_mb' => $lastHealth?->database_size_mb,
                'connection_count' => $lastHealth?->connection_count,
            ],
            'table_sizes' => $tableSizes,
            'query_baselines' => $queryBaselines,
            'active_patterns' => [
                'workloads' => config('intelligence.performance_budgets', []),
            ],
            'knowledge_version' => config('intelligence.knowledge_version'),
            'captured_at' => now(),
        ]);
    }

    public function latest(): ?BaselineSnapshot
    {
        return BaselineSnapshot::query()->orderByDesc('captured_at')->first();
    }
}
