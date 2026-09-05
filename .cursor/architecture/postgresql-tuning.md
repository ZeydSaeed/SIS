# PostgreSQL Tuning — 45K Student Production

> **Hardware baseline:** 16 vCPU, 32 GB RAM, 500 GB NVMe SSD  
> **Scale:** 45,000 active students, 450M attendance rows over 10 years

## postgresql.conf — Production Settings

```ini
# Memory
shared_buffers = 8GB                  # 25% of 32GB RAM
effective_cache_size = 24GB           # 75% of RAM
work_mem = 64MB                       # Per sort/hash operation
maintenance_work_mem = 2GB            # VACUUM, CREATE INDEX
huge_pages = try

# Connections (with PgBouncer)
max_connections = 200                 # PgBouncer pools to this
superuser_reserved_connections = 3

# WAL
wal_level = replica                   # Required for replication
max_wal_size = 4GB
min_wal_size = 1GB
checkpoint_completion_target = 0.9

# Query Planner (SSD)
random_page_cost = 1.1
effective_io_concurrency = 200
default_statistics_target = 200       # Better plans for large tables

# Parallelism
max_parallel_workers_per_gather = 4
max_parallel_workers = 8
max_worker_processes = 16

# Autovacuum — critical for attendance table
autovacuum = on
autovacuum_max_workers = 4
autovacuum_naptime = 30s
autovacuum_vacuum_scale_factor = 0.05   # Vacuum at 5% dead tuples
autovacuum_analyze_scale_factor = 0.02
```

## Per-Table Autovacuum Overrides

High-churn tables need aggressive autovacuum:

```sql
ALTER TABLE attendance.records SET (
    autovacuum_vacuum_scale_factor = 0.02,
    autovacuum_analyze_scale_factor = 0.01,
    autovacuum_vacuum_cost_delay = 10
);

ALTER TABLE exams.student_grades SET (
    autovacuum_vacuum_scale_factor = 0.05,
    autovacuum_analyze_scale_factor = 0.02
);

ALTER TABLE audit.audit_logs SET (
    autovacuum_vacuum_scale_factor = 0.01,
    autovacuum_analyze_scale_factor = 0.01
);
```

## PgBouncer Configuration

```ini
[databases]
sis = host=127.0.0.1 port=5432 dbname=sis

[pgbouncer]
pool_mode = transaction
max_client_conn = 2000
default_pool_size = 50
min_pool_size = 10
reserve_pool_size = 10
reserve_pool_timeout = 3
server_reset_query = DISCARD ALL
```

Laravel `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=6432          # PgBouncer port, not 5432
```

## Laravel Database Config

```php
// config/database.php — pgsql connection
'search_path' => env('DB_SEARCH_PATH', 'public,organization,academic,vocational,students,guardians,enrollment,teachers,curriculum,timetable,attendance,exams,results,security,audit,reports'),
```

## Monitoring Queries

### Cache Hit Ratio (target > 99%)

```sql
SELECT
    sum(heap_blks_hit) / nullif(sum(heap_blks_hit + heap_blks_read), 0) AS cache_hit_ratio
FROM pg_statio_user_tables;
```

### Table Bloat

```sql
SELECT schemaname, relname, n_dead_tup, n_live_tup,
       round(n_dead_tup * 100.0 / nullif(n_live_tup + n_dead_tup, 0), 2) AS dead_pct
FROM pg_stat_user_tables
WHERE n_dead_tup > 10000
ORDER BY n_dead_tup DESC;
```

### Slow Queries (enable pg_stat_statements)

```sql
SELECT query, calls, mean_exec_time, total_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 20;
```

### Partition Pruning Verification

```sql
EXPLAIN ANALYZE
SELECT * FROM attendance.records
WHERE academic_year_id = 1 AND attendance_date = '2026-01-15';
-- Must show: Seq Scan on records_2026 (not full table scan)
```

## Index Maintenance Schedule

| Action | Frequency | Command |
|--------|-----------|---------|
| ANALYZE | After bulk ops | `ANALYZE attendance.records` |
| REINDEX | When dead_pct > 30% | `REINDEX INDEX CONCURRENTLY ...` |
| VACUUM | Autovacuum handles | Monitor via pg_stat |
| Partition maintenance | Yearly | Create new partition before academic year |

## Connection Limits Calculation

```
App servers: 2 × 8 PHP-FPM workers = 16 processes
Queue workers: 8 Horizon workers
Each process: ~5 connections peak
Total app demand: ~120 connections
PgBouncer pool: 50–80 to PostgreSQL
PostgreSQL max_connections: 200 (includes replica + admin)
```

## Backup Configuration

```bash
# Daily full backup + continuous WAL
pg_basebackup -h localhost -U replication -D /backup/base -Ft -z -P
# WAL archive in postgresql.conf:
archive_mode = on
archive_command = 'cp %p /backup/wal/%f'
```

Target: RPO 15 minutes, RTO 1 hour.
