<?php

namespace App\Infrastructure\Observability;

use App\Application\Observability\Contracts\HttpWorkloadReadRepositoryInterface;
use App\Intelligence\Models\MonitoringSnapshot;
use Illuminate\Support\Facades\Schema;

final class EloquentHttpWorkloadReadRepository implements HttpWorkloadReadRepositoryInterface
{
    public function latestSummary(): ?array
    {
        $table = (new MonitoringSnapshot)->getTable();

        if (! Schema::hasTable($table)) {
            return null;
        }

        $snapshot = MonitoringSnapshot::query()
            ->where('snapshot_type', 'http_workload')
            ->orderByDesc('captured_at')
            ->first();

        if ($snapshot === null || ! is_array($snapshot->metrics)) {
            return null;
        }

        return [
            'workload' => $snapshot->metrics['workload'] ?? null,
            'p95_ms' => $snapshot->metrics['p95_ms'] ?? null,
            'sample_count' => $snapshot->metrics['sample_count'] ?? null,
            'budget_exceeded' => $snapshot->metrics['budget_exceeded'] ?? null,
            'captured_at' => $snapshot->captured_at?->toIso8601String(),
        ];
    }

    public function latestPerWorkload(int $limit = 50): array
    {
        $table = (new MonitoringSnapshot)->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        $rows = MonitoringSnapshot::query()
            ->where('snapshot_type', 'http_workload')
            ->orderByDesc('captured_at')
            ->limit(max(1, $limit) * 5)
            ->get(['metrics', 'captured_at']);

        $byWorkload = [];
        foreach ($rows as $snapshot) {
            if (! is_array($snapshot->metrics)) {
                continue;
            }
            $workload = $snapshot->metrics['workload'] ?? null;
            if (! is_string($workload) || $workload === '' || isset($byWorkload[$workload])) {
                continue;
            }
            $byWorkload[$workload] = [
                'workload' => $workload,
                'p95_ms' => isset($snapshot->metrics['p95_ms']) ? (float) $snapshot->metrics['p95_ms'] : null,
                'sample_count' => isset($snapshot->metrics['sample_count']) ? (int) $snapshot->metrics['sample_count'] : null,
                'budget_exceeded' => isset($snapshot->metrics['budget_exceeded'])
                    ? (bool) $snapshot->metrics['budget_exceeded']
                    : null,
                'captured_at' => $snapshot->captured_at?->toIso8601String(),
            ];
            if (count($byWorkload) >= $limit) {
                break;
            }
        }

        return array_values($byWorkload);
    }
}
