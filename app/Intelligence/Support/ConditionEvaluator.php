<?php

namespace App\Intelligence\Support;

class ConditionEvaluator
{
    /**
     * @param  array<string, string>  $condition  e.g. ['table_size_gb' => '> 10']
     * @param  array<string, float|int|bool|null>  $metrics
     */
    public function matches(array $condition, array $metrics): bool
    {
        foreach ($condition as $key => $expression) {
            if (! array_key_exists($key, $metrics)) {
                return false;
            }

            if (! $this->evaluateExpression($metrics[$key], $expression)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateExpression(mixed $value, string $expression): bool
    {
        if ($expression === 'true') {
            return (bool) $value;
        }

        if ($expression === 'false') {
            return ! (bool) $value;
        }

        if (preg_match('/^(>=|<=|>|<|=)\s*(.+)$/', trim($expression), $matches) !== 1) {
            return false;
        }

        [, $operator, $thresholdRaw] = $matches;
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : $thresholdRaw;

        return match ($operator) {
            '>' => (float) $value > (float) $threshold,
            '>=' => (float) $value >= (float) $threshold,
            '<' => (float) $value < (float) $threshold,
            '<=' => (float) $value <= (float) $threshold,
            '=' => $value == $threshold,
            default => false,
        };
    }
}
