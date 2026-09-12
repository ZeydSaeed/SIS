<?php

namespace App\Application\Observability\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Observability\Contracts\DatabaseHealthPort;
use App\Application\Observability\Contracts\HttpWorkloadReadRepositoryInterface;
use App\Application\Observability\DTOs\PrometheusMetricsDTO;
use App\Application\Observability\Support\PrometheusTextBuilder;

final class GetPrometheusMetricsHandler implements QueryHandler
{
    public function __construct(
        private readonly DatabaseHealthPort $databaseHealth,
        private readonly HttpWorkloadReadRepositoryInterface $httpWorkloads,
    ) {}

    public function handle(Query $query): PrometheusMetricsDTO
    {
        assert($query instanceof GetPrometheusMetricsQuery);

        $builder = new PrometheusTextBuilder;
        $up = $this->databaseHealth->isAvailable() ? 1 : 0;

        $builder->help('sis_up', '1 if the primary database probe succeeds');
        $builder->type('sis_up', 'gauge');
        $builder->gauge('sis_up', $up);

        $builder->help('sis_info', 'Static SIS build/environment labels');
        $builder->type('sis_info', 'gauge');
        $builder->gauge('sis_info', 1, [
            'version' => (string) config('sis.api.version', 'unknown'),
            'environment' => (string) config('app.env', 'unknown'),
            'profile' => (string) config('sis.environment_profile', 'unknown'),
        ]);

        $builder->help('sis_process_memory_bytes', 'PHP memory_get_usage(true)');
        $builder->type('sis_process_memory_bytes', 'gauge');
        $builder->gauge('sis_process_memory_bytes', memory_get_usage(true));

        $builder->help('sis_http_workload_p95_ms', 'Latest flushed HTTP workload p95 latency in milliseconds');
        $builder->type('sis_http_workload_p95_ms', 'gauge');
        $builder->help('sis_http_workload_samples', 'Sample count from latest HTTP workload snapshot');
        $builder->type('sis_http_workload_samples', 'gauge');
        $builder->help('sis_http_workload_budget_exceeded', '1 if latest snapshot exceeded configured p95 budget');
        $builder->type('sis_http_workload_budget_exceeded', 'gauge');

        foreach ($this->httpWorkloads->latestPerWorkload() as $row) {
            $labels = ['workload' => $row['workload']];
            if ($row['p95_ms'] !== null) {
                $builder->gauge('sis_http_workload_p95_ms', $row['p95_ms'], $labels);
            }
            if ($row['sample_count'] !== null) {
                $builder->gauge('sis_http_workload_samples', $row['sample_count'], $labels);
            }
            if ($row['budget_exceeded'] !== null) {
                $builder->gauge('sis_http_workload_budget_exceeded', $row['budget_exceeded'] ? 1 : 0, $labels);
            }
        }

        $builder->help('sis_optimization_observe_mode', '1 when optimization.mode is observe');
        $builder->type('sis_optimization_observe_mode', 'gauge');
        $builder->gauge(
            'sis_optimization_observe_mode',
            (string) config('optimization.mode') === 'observe' ? 1 : 0,
        );

        return new PrometheusMetricsDTO(body: $builder->build());
    }
}
