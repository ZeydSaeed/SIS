<?php

namespace App\Intelligence\Monitoring;

use App\Database\SchemaHelper;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\TableMetric;
use App\Intelligence\Support\CorrelationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseMonitor
{
    public function collectHealthSnapshot(): MonitoringSnapshot
    {
        $metrics = [
            'driver' => DB::connection()->getDriverName(),
            'postgres' => SchemaHelper::isPostgreSql(),
        ];

        $databaseSizeMb = null;
        $cacheHitRatio = null;
        $connectionCount = null;
        $activeConnections = null;
        $replicationLag = null;

        if (SchemaHelper::isPostgreSql()) {
            $databaseSizeMb = $this->scalar(
                'SELECT ROUND(pg_database_size(current_database()) / 1024.0 / 1024.0, 2)'
            );

            $cacheHitRatio = $this->scalar('
                SELECT ROUND(
                    sum(blks_hit)::numeric / NULLIF(sum(blks_hit + blks_read), 0),
                    4
                )
                FROM pg_stat_database
                WHERE datname = current_database()
            ');

            $connectionCount = (int) $this->scalar('SELECT count(*) FROM pg_stat_activity');
            $activeConnections = (int) $this->scalar("
                SELECT count(*) FROM pg_stat_activity WHERE state = 'active'
            ");

            $replicationLag = $this->scalar('
                SELECT COALESCE(
                    EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp())),
                    0
                )
            ');
        } else {
            $databaseSizeMb = $this->sqliteSizeMb();
        }

        return MonitoringSnapshot::query()->create([
            'snapshot_type' => 'health',
            'database_size_mb' => $databaseSizeMb,
            'connection_count' => $connectionCount,
            'active_connections' => $activeConnections,
            'cache_hit_ratio' => $cacheHitRatio,
            'replication_lag_seconds' => $replicationLag,
            'metrics' => $metrics,
            'correlation_id' => CorrelationContext::id(),
            'captured_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, TableMetric>
     */
    public function collectTableMetrics(): Collection
    {
        if (! SchemaHelper::isPostgreSql()) {
            return collect();
        }

        $rows = DB::select("
            SELECT
                schemaname AS schema_name,
                relname AS table_name,
                n_live_tup AS row_estimate,
                ROUND(pg_total_relation_size(relid) / 1024.0 / 1024.0, 2) AS total_size_mb,
                ROUND(pg_relation_size(relid) / 1024.0 / 1024.0, 2) AS table_size_mb,
                ROUND(pg_indexes_size(relid) / 1024.0 / 1024.0, 2) AS index_size_mb,
                seq_scan,
                idx_scan,
                CASE
                    WHEN (seq_scan + idx_scan) = 0 THEN 0
                    ELSE ROUND(seq_scan::numeric / (seq_scan + idx_scan), 4)
                END AS seq_scan_ratio,
                EXTRACT(EPOCH FROM (now() - GREATEST(last_vacuum, last_autovacuum, last_analyze, last_autoanalyze))) / 3600 AS stats_age_hours
            FROM pg_stat_user_tables
            WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
            ORDER BY pg_total_relation_size(relid) DESC
            LIMIT 100
        ");

        $metrics = collect();

        foreach ($rows as $row) {
            $previous = TableMetric::query()
                ->where('schema_name', $row->schema_name)
                ->where('table_name', $row->table_name)
                ->orderByDesc('captured_at')
                ->first();

            $growthRate = null;
            if ($previous && (float) $previous->table_size_mb > 0) {
                $growthRate = round(
                    (((float) $row->table_size_mb - (float) $previous->table_size_mb) / (float) $previous->table_size_mb) * 100,
                    2
                );
            }

            $metrics->push(TableMetric::query()->create([
                'schema_name' => $row->schema_name,
                'table_name' => $row->table_name,
                'row_estimate' => (int) $row->row_estimate,
                'table_size_mb' => $row->table_size_mb,
                'index_size_mb' => $row->index_size_mb,
                'seq_scan_ratio' => $row->seq_scan_ratio,
                'seq_scans' => (int) $row->seq_scan,
                'idx_scans' => (int) $row->idx_scan,
                'growth_rate_daily_pct' => $growthRate,
                'context' => ['stats_age_hours' => $row->stats_age_hours ?? null],
                'captured_at' => now(),
            ]));
        }

        return $metrics;
    }

    private function scalar(string $sql): mixed
    {
        $result = DB::selectOne($sql);

        return $result ? array_values((array) $result)[0] : null;
    }

    private function sqliteSizeMb(): ?float
    {
        $path = config('database.connections.sqlite.database');

        if (! is_string($path) || ! file_exists($path)) {
            return null;
        }

        return round(filesize($path) / 1024 / 1024, 2);
    }
}
