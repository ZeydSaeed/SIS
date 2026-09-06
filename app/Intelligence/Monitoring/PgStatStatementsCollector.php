<?php

namespace App\Intelligence\Monitoring;

use App\Database\SchemaHelper;
use App\Intelligence\Models\QueryMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PgStatStatementsCollector
{
    /**
     * @return Collection<int, QueryMetric>
     */
    public function collect(): Collection
    {
        if (! SchemaHelper::isPostgreSql() || ! config('intelligence.pg_stat_statements.enabled', true)) {
            return collect();
        }

        if (! $this->extensionAvailable()) {
            Log::info('Intelligence: pg_stat_statements extension not available — run migration 2026_09_06_101000');

            return collect();
        }

        $limit = (int) config('intelligence.pg_stat_statements.top_queries', 50);

        $rows = DB::select("
            SELECT
                queryid::text AS query_id,
                LEFT(query, 512) AS query_text,
                calls,
                ROUND(mean_exec_time::numeric, 2) AS mean_ms,
                ROUND((total_exec_time / NULLIF(calls, 0))::numeric, 2) AS avg_ms,
                ROUND(max_exec_time::numeric, 2) AS max_ms,
                rows,
                shared_blks_hit,
                shared_blks_read
            FROM pg_stat_statements
            WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
              AND query NOT ILIKE '%pg_stat_statements%'
              AND calls > 0
            ORDER BY mean_exec_time DESC
            LIMIT ?
        ", [$limit]);

        $metrics = collect();

        foreach ($rows as $row) {
            $fingerprint = hash('sha256', preg_replace('/\s+/', ' ', (string) $row->query_text) ?? (string) $row->query_id);
            $p95Estimate = (float) $row->max_ms * 0.85;

            $baselineKey = "intelligence.baseline.pgstat.{$fingerprint}";
            $baseline = cache()->get($baselineKey, $p95Estimate);
            if (! cache()->has($baselineKey)) {
                cache()->forever($baselineKey, $p95Estimate);
            }

            $degradation = $baseline > 0
                ? round((($p95Estimate - $baseline) / $baseline) * 100, 2)
                : 0;

            $metrics->push(QueryMetric::query()->create([
                'query_fingerprint' => $fingerprint,
                'query_label' => $this->labelQuery((string) $row->query_text),
                'call_count' => (int) $row->calls,
                'p50_ms' => (float) $row->mean_ms,
                'p95_ms' => $p95Estimate,
                'p99_ms' => (float) $row->max_ms,
                'mean_ms' => (float) $row->avg_ms,
                'baseline_p95_ms' => $baseline,
                'degradation_pct' => $degradation,
                'workload_class' => $this->inferWorkload((string) $row->query_text, (int) $row->calls),
                'context' => [
                    'source' => 'pg_stat_statements',
                    'query_id' => $row->query_id,
                    'shared_blks_hit' => (int) $row->shared_blks_hit,
                    'shared_blks_read' => (int) $row->shared_blks_read,
                    'rows' => (int) $row->rows,
                ],
                'captured_at' => now(),
            ]));
        }

        return $metrics;
    }

    public function extensionAvailable(): bool
    {
        if (! SchemaHelper::isPostgreSql()) {
            return false;
        }

        $result = DB::selectOne("
            SELECT 1
            FROM pg_extension
            WHERE extname = 'pg_stat_statements'
        ");

        return $result !== null;
    }

    private function labelQuery(string $query): string
    {
        $normalized = strtoupper(trim($query));

        if (str_contains($normalized, 'FROM ENROLLMENT.ENROLLMENTS') || str_contains($normalized, 'FROM ENROLLMENT_ENROLLMENTS')) {
            return 'enrollment_lookup';
        }

        if (str_contains($normalized, 'FROM ATTENDANCE.RECORDS') || str_contains($normalized, 'FROM ATTENDANCE_RECORDS')) {
            return 'attendance_query';
        }

        if (str_contains($normalized, 'FROM STUDENTS.STUDENTS') || str_contains($normalized, 'FROM STUDENTS_STUDENTS')) {
            return 'student_search';
        }

        return substr($normalized, 0, 80);
    }

    private function inferWorkload(string $query, int $calls): string
    {
        $normalized = strtoupper($query);

        if (str_contains($normalized, 'INSERT') || str_contains($normalized, 'COPY')) {
            return 'bulk';
        }

        if (str_contains($normalized, 'GROUP BY') || str_contains($normalized, 'COUNT(')) {
            return 'dashboard';
        }

        if ($calls >= 1000) {
            return 'oltp';
        }

        return 'mixed';
    }
}
