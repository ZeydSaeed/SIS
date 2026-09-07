<?php

namespace App\Optimization\Execution;

use App\Intelligence\Models\Recommendation;

/**
 * Validates and builds trusted ANALYZE targets — single source for allowlist + SQL quoting.
 */
final class AnalyzeTargetPolicy
{
    /**
     * @return array{schema: string, table: string, qualified: string}|null
     */
    public function resolveFromRecommendation(Recommendation $recommendation): ?array
    {
        if (! $recommendation->schema_name || ! $recommendation->table_name) {
            return null;
        }

        return $this->resolve(
            (string) $recommendation->schema_name,
            (string) $recommendation->table_name,
        );
    }

    /**
     * @return array{schema: string, table: string, qualified: string}|null
     */
    public function resolve(string $schemaName, string $tableName): ?array
    {
        $schema = $this->sanitizeIdentifier($schemaName);
        $table = $this->sanitizeIdentifier($tableName);

        if ($schema === null || $table === null) {
            return null;
        }

        return [
            'schema' => $schema,
            'table' => $table,
            'qualified' => "{$schema}.{$table}",
        ];
    }

    public function isAllowlisted(string $schema, string $table): bool
    {
        $resolved = $this->resolve($schema, $table);
        if ($resolved === null) {
            return false;
        }

        $allowed = config('optimization.analyze.allowed_targets', []);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array(
            $resolved['qualified'],
            array_map(fn (string $t) => strtolower(trim($t)), $allowed),
            true,
        );
    }

    /**
     * PostgreSQL-safe quoted identifier form for ANALYZE.
     */
    public function toAnalyzeSql(string $schema, string $table): ?string
    {
        $resolved = $this->resolve($schema, $table);
        if ($resolved === null || ! $this->isAllowlisted($schema, $table)) {
            return null;
        }

        return '"'.$resolved['schema'].'"."'.$resolved['table'].'"';
    }

    public function sanitizeIdentifier(string $raw): ?string
    {
        $normalized = strtolower(trim($raw));
        $normalized = str_replace('"', '', $normalized);

        if ($normalized === '' || ! $this->isValidIdentifier($normalized)) {
            return null;
        }

        return $normalized;
    }

    private function isValidIdentifier(string $name): bool
    {
        return (bool) preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $name);
    }
}
