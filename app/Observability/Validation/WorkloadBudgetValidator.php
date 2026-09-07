<?php

namespace App\Observability\Validation;

final class WorkloadBudgetValidator
{
    /**
     * @param  array<string, mixed>  $metrics
     */
    public function validate(string $workload, array $metrics): WorkloadValidationReport
    {
        $budget = config("intelligence.performance_budgets.{$workload}", []);
        $checks = [];

        $checks[] = $this->checkNumericMax(
            name: 'p95_latency',
            actual: $metrics['p95_ms'] ?? null,
            limit: $budget['p95_ms'] ?? null,
            unit: 'ms',
        );

        $checks[] = $this->checkNumericMax(
            name: 'p99_latency',
            actual: $metrics['p99_ms'] ?? null,
            limit: $budget['p99_ms'] ?? null,
            unit: 'ms',
        );

        $checks[] = $this->checkNumericMax(
            name: 'mean_db_queries_per_request',
            actual: $metrics['mean_db_queries_per_request'] ?? null,
            limit: $budget['max_db_queries_per_request'] ?? null,
            unit: 'queries',
        );

        $checks[] = $this->checkBoolean(
            name: 'budget_exceeded',
            actual: $metrics['budget_exceeded'] ?? null,
            expected: false,
        );

        $checks[] = $this->checkNumericMax(
            name: 'error_rate_pct',
            actual: $metrics['error_rate_pct'] ?? null,
            limit: 0.5,
            unit: '%',
        );

        $checks[] = $this->checkMinimumSamples(
            actual: $metrics['sample_count'] ?? null,
            minimum: 5,
        );

        $passed = collect($checks)->every(
            fn (array $check): bool => $check['status'] === 'pass' || $check['status'] === 'skip',
        );

        return new WorkloadValidationReport(
            workload: $workload,
            passed: $passed,
            checks: $checks,
            metrics: $metrics,
            performanceBudgetVersion: (string) config('intelligence.performance_budget_version'),
        );
    }

    /**
     * @return array{name: string, status: string, expected: mixed, actual: mixed, detail: string}
     */
    private function checkNumericMax(string $name, mixed $actual, mixed $limit, string $unit): array
    {
        if ($limit === null || ! is_numeric($limit)) {
            return [
                'name' => $name,
                'status' => 'skip',
                'expected' => null,
                'actual' => $actual,
                'detail' => 'No budget limit configured',
            ];
        }

        if (! is_numeric($actual)) {
            return [
                'name' => $name,
                'status' => 'fail',
                'expected' => '<= '.$limit.' '.$unit,
                'actual' => $actual,
                'detail' => 'Metric unavailable — cannot validate',
            ];
        }

        $passed = (float) $actual <= (float) $limit;

        return [
            'name' => $name,
            'status' => $passed ? 'pass' : 'fail',
            'expected' => '<= '.$limit.' '.$unit,
            'actual' => $actual,
            'detail' => $passed
                ? 'Within budget'
                : sprintf('Exceeded budget (%s %s > %s %s)', $actual, $unit, $limit, $unit),
        ];
    }

    /**
     * @return array{name: string, status: string, expected: mixed, actual: mixed, detail: string}
     */
    private function checkBoolean(string $name, mixed $actual, bool $expected): array
    {
        if ($actual === null) {
            return [
                'name' => $name,
                'status' => 'skip',
                'expected' => $expected,
                'actual' => null,
                'detail' => 'Metric unavailable',
            ];
        }

        $passed = (bool) $actual === $expected;

        return [
            'name' => $name,
            'status' => $passed ? 'pass' : 'fail',
            'expected' => $expected,
            'actual' => (bool) $actual,
            'detail' => $passed ? 'Budget not exceeded' : 'Performance budget exceeded',
        ];
    }

    /**
     * @return array{name: string, status: string, expected: mixed, actual: mixed, detail: string}
     */
    private function checkMinimumSamples(mixed $actual, int $minimum): array
    {
        if (! is_numeric($actual)) {
            return [
                'name' => 'sample_count',
                'status' => 'fail',
                'expected' => '>= '.$minimum,
                'actual' => $actual,
                'detail' => 'Insufficient samples for reliable validation',
            ];
        }

        $passed = (int) $actual >= $minimum;

        return [
            'name' => 'sample_count',
            'status' => $passed ? 'pass' : 'fail',
            'expected' => '>= '.$minimum,
            'actual' => (int) $actual,
            'detail' => $passed ? 'Sample size sufficient' : 'Too few samples',
        ];
    }
}
