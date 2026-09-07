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
}
