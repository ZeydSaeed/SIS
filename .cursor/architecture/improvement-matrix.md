# Improvement Priority Matrix

## Full Matrix

| # | Improvement | Priority | When to Apply |
|---|-------------|----------|---------------|
| 1 | Normalization (3NF) | **P0** | From day one |
| 2 | FK Integrity | **P0** | From day one |
| 3 | Composite Indexes | **P0** | From day one (core queries) |
| 4 | Optimized Data Types | **P0** | From day one |
| 5 | Sequential BIGINT IDs | **P0** | From day one |
| 6 | CHECK Constraints | **P0** | From day one |
| 7 | Audit Trail | **P0** | From day one |
| 8 | Schema Migrations | **P0** | From day one |
| 9 | Autovacuum tuning | **P0** | From production |
| 10 | ANALYZE | **P0** | Automatic + after bulk ops |
| 11 | Backup + PITR | **P0** | Before production |
| 12 | Monitoring + Alerting | **P0** | Before production |
| 13 | Load Testing | **P0** | Before production |
| 14 | Disaster Recovery plan | **P0** | Before production |
| 15 | Covering Indexes | **P1** | Query-driven (measure first) |
| 16 | Partial Indexes | **P1** | When inactive data > 70% |
| 17 | Redis caching | **P1** | Reference data + dashboards |
| 18 | Async Queues | **P1** | Heavy operations |
| 19 | Materialized Views | **P1** | Dashboard + reports |
| 20 | Partitioning | **P1** | Tables > 10M rows |
| 21 | PgBouncer | **P1** | Multi-node / high connections |
| 22 | RLS | **P1/P2** | Multi-tenant isolation needed |
| 23 | Data Archiving | **P1** | After 5+ years of data |
| 24 | Read Replica | **P2** | Read load exceeds primary |
| 25 | Reporting DB | **P2** | Analytics load justifies separation |
| 26 | REINDEX | **P2** | When bloat detected |

## Implementation Order by Module

When building the application, implement domains in this order:

```
Phase A — Foundation
  1. organization (schools, branches)
  2. academic (years, terms, grade levels)
  3. security (users, roles, permissions)

Phase B — Core Academic
  4. students + guardians
  5. enrollment (classes, sections, enrollments)
  6. curriculum (subjects, curricula)
  7. teachers

Phase C — Daily Operations
  8. timetable
  9. attendance
  10. exams + results

Phase D — Lifecycle
  11. admission
  12. promotion + transfers
  13. graduation + certificates

Phase E — Supporting
  14. finance
  15. communication + workflow
  16. documents
  17. audit (parallel with all phases)

Phase F — Analytics
  18. reports (materialized views)
  19. Redis caching layer
  20. Async queue infrastructure
```

## What Changed from v1 Architecture

| v1 Concept | v2 Correction |
|-----------|--------------|
| TINYINT | smallint (PostgreSQL) |
| Filtered Index | Partial Index |
| Index Reorganize | VACUUM / ANALYZE / REINDEX |
| Random UUID everywhere | BIGINT IDENTITY default; UUID for external IDs |
| Soft delete everywhere | status + effective_to + archived_at |
| 10 servers day one | Phase 1: App + Redis + PostgreSQL |
| "Fast for 20 years" | Architected for 20+ years data growth |
| Reports on OLTP | Materialized views + reporting layer |
| Sync heavy operations | Async queues with progress UI |

## Four Layers Summary

```
Layer 1 — Data Integrity (P0)
  Normalization, PK/FK, Constraints, Transactions, Audit

Layer 2 — Performance (P0–P1)
  Data Types, Indexes, Partitioning, Query Optimization

Layer 3 — Scalability (P1–P2)
  Redis, PgBouncer, Queues, Replicas, Materialized Views

Layer 4 — Resilience (P0)
  Backup, PITR, DR, Monitoring, Archiving, Load Testing
```

## Success Criteria

The architecture is successful when:

- [ ] Student lifecycle fully traceable across 20 years
- [ ] No official academic record ever hard-deleted
- [ ] All migrations versioned and reviewed
- [ ] P95 API response < 500ms for core operations
- [ ] Heavy operations complete via queue without blocking UI
- [ ] Dashboard reads from materialized views, not OLTP joins
- [ ] Backup restore tested and documented
- [ ] Load test breaking point documented
- [ ] Every slow query has an EXPLAIN ANALYZE on file
