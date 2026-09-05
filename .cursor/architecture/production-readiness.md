# Production Readiness — Phase D (45K Student Deployment)

> Execute this checklist before go-live.  
> Infrastructure target: Phase 2 from day one (see [02-infrastructure-phases.md](./02-infrastructure-phases.md))

## Infrastructure Checklist

- [ ] PostgreSQL Primary: 16 vCPU, 32 GB RAM, 500 GB NVMe
- [ ] Read Replica configured and streaming
- [ ] PgBouncer between app and PostgreSQL (port 6432)
- [ ] Redis: 4 GB for cache + queue
- [ ] 2× App servers behind load balancer
- [ ] Object storage for documents (S3-compatible)
- [ ] WAL archiving enabled for PITR
- [ ] DR environment tested

## Database Checklist

- [ ] All Phase A–C migrations applied
- [ ] attendance.records partitioned by academic_year_id
- [ ] RLS enabled on enrollment.enrollments, attendance.records
- [ ] daily_section_summary populated
- [ ] Materialized views created and indexed
- [ ] Autovacuum tuned per [postgresql-tuning.md](./postgresql-tuning.md)
- [ ] pg_stat_statements enabled
- [ ] Backup restore tested (full + PITR to specific timestamp)

## Application Checklist

- [ ] AttendanceBatchService used for all attendance writes
- [ ] Queue workers: 8 for attendance-writes during peak
- [ ] Horizon or queue monitoring configured
- [ ] Correlation ID middleware active
- [ ] Audit logging on academic record changes
- [ ] Idempotency keys on bulk operations
- [ ] DB_CONNECTION=pgsql in production .env
- [ ] CACHE_STORE=redis, QUEUE_CONNECTION=redis

## Load Testing Checklist

Run before production (see [capacity-planning-45k.md](./capacity-planning-45k.md)):

| # | Scenario | Concurrent | Pass |
|---|----------|-----------|------|
| 1 | Login | 500 | P95 < 500ms |
| 2 | Student search | 200 | P95 < 200ms |
| 3 | Attendance batch (50 students) | 900 | All complete < 2s each |
| 4 | 45K attendance bulk insert | 1 job | < 60 seconds |
| 5 | School dashboard | 20 | P95 < 1s |
| 6 | Directorate dashboard | 5 | P95 < 3s |
| 7 | Stress test | 2,000 | Document breaking point |

Record results in `.cursor/architecture/load-test-results.md` after testing.

## Monitoring & Alerts

| Metric | Warning | Critical |
|--------|---------|----------|
| CPU | > 70% | > 85% |
| Disk | > 70% | > 85% |
| DB connections | > 70% | > 90% |
| Replication lag | > 30s | > 120s |
| Queue depth (attendance) | > 50 | > 200 |
| Cache hit ratio | < 98% | < 95% |
| Backup age | > 25h | Failed |
| P95 API response | > 800ms | > 2s |

Tools: Prometheus + Grafana, or Laravel Pulse + pg_stat_statements.

## SLA Commitments

| Operation | P95 | Availability |
|-----------|-----|-------------|
| Core CRUD (enrollment, profile) | < 500ms | 99.5% |
| Attendance submission | < 2s | 99.5% |
| Dashboards | < 3s | 99% |
| Bulk reports | Async (< 5 min) | 99% |

## Rollback Plan

1. Stop app servers
2. Restore PostgreSQL to last known good (PITR)
3. Verify migration version matches application
4. Restart with previous application release
5. Document incident in audit log

## Post-Launch (First 30 Days)

- [ ] Daily: check slow query log
- [ ] Daily: verify backup success
- [ ] Weekly: REVIEW partition growth
- [ ] Weekly: ANALYZE high-churn tables if bulk ops occurred
- [ ] Monthly: full restore test
- [ ] End of year: create new attendance partition before enrollment
