<?php

namespace App\Observability\Validation;

final readonly class WorkloadValidationReport
{
    /**
     * @param  list<array{name: string, status: string, expected: mixed, actual: mixed, detail: string}>  $checks
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public string $workload,
        public bool $passed,
        public array $checks,
        public array $metrics,
        public string $performanceBudgetVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'workload' => $this->workload,
            'passed' => $this->passed,
            'performance_budget_version' => $this->performanceBudgetVersion,
            'checks' => $this->checks,
            'metrics' => $this->metrics,
        ];
    }
}
