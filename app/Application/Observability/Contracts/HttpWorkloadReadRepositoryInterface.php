<?php

namespace App\Application\Observability\Contracts;

interface HttpWorkloadReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function latestSummary(): ?array;

    /**
     * Latest http_workload snapshot per workload name (most recent first within each group).
     *
     * @return list<array{
     *     workload: string,
     *     p95_ms: float|null,
     *     sample_count: int|null,
     *     budget_exceeded: bool|null,
     *     captured_at: string|null
     * }>
     */
    public function latestPerWorkload(int $limit = 50): array;
}
