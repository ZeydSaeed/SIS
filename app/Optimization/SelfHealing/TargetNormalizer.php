<?php

namespace App\Optimization\SelfHealing;

use App\Optimization\Execution\AnalyzeTargetPolicy;

/**
 * Normalizes optimization target strings to a stable lock/compare key.
 */
final class TargetNormalizer
{
    public function __construct(
        private readonly AnalyzeTargetPolicy $targetPolicy,
    ) {}

    public function normalize(string $target, ?string $schemaName = null, ?string $tableName = null): string
    {
        if ($schemaName !== null && $tableName !== null) {
            $resolved = $this->targetPolicy->resolve($schemaName, $tableName);

            return $resolved['qualified'] ?? strtolower(trim($schemaName)).'.'.strtolower(trim($tableName));
        }

        $normalized = strtolower(trim($target));
        $normalized = str_replace('"', '', $normalized);

        if (str_contains($normalized, '.')) {
            [$schema, $table] = explode('.', $normalized, 2);

            return trim($schema).'.'.trim($table);
        }

        return $normalized;
    }

    public function lockKey(string $target, ?string $schemaName = null, ?string $tableName = null): string
    {
        return 'optimization:target-lock:'.hash('sha256', $this->normalize($target, $schemaName, $tableName));
    }
}
