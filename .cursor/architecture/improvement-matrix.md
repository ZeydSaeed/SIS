# Improvement Priority Matrix

> **Updated for:** Adaptive governance + 45K baseline (20 schools × 10 years)  
> **See also:** [capacity-planning.md](./capacity-planning.md) (authoritative) · [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)

## Full Matrix — Standard

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

## Priority Overrides — 45K Student Scenario

These items change priority for the province deployment:

| # | Improvement | Standard | **45K Scenario** | Reason |
|---|-------------|----------|-----------------|--------|
| 20 | Partitioning | P1 (>10M) | **P0 — Year 1** | 45M attendance rows/year |
| 21 | PgBouncer | P1 | **P0 — Launch** | 2000 concurrent connections |
| 22 | RLS | P1/P2 | **P0 — Launch** | 20-school data isolation |
| 24 | Read Replica | P2 | **P0 — Launch** | Directorate reports |
| 19 | Materialized Views | P1 | **P0 — Launch** | 20-school dashboards |
| — | daily_section_summary | N/A | **P0 — New** | Dashboard without 45K scan |
| — | Batch write pattern | N/A | **P0 — New** | 45K morning attendance |
| — | PostgreSQL tuning | N/A | **P0 — Launch** | Hardware utilization |
| — | Peak hour strategy | N/A | **P0 — Launch** | 8:00–8:30 window |

## New Improvements (45K-Specific)

| # | Improvement | Priority | Reference |
|---|-------------|----------|-----------|
| 27 | daily_section_summary table | **P0** | database-blueprint.md |
| 28 | Batch attendance writes | **P0** | batch-write-patterns.md |
| 29 | Peak hour queue scaling | **P0** | peak-hour-strategy.md |
| 30 | PostgreSQL production tuning | **P0** | postgresql-tuning.md |
| 31 | Capacity planning (dynamic) | **P0** | capacity-planning.md |
| 31b | Capacity baseline 45K snapshot | **P0** | capacity-planning-45k.md |
| 32 | Production readiness checklist | **P0** | production-readiness.md |
| 33 | Phase implementation guides | **P0** | phases/PHASE-*.md |
| 34 | RLS policies (20 schools) | **P0** | rls-policies.md |
| 35 | Load test template | **P0** | load-test-results.md |
| 36 | ERD overview | **P1** | erd-overview.md |
| 37 | Database dictionary | **P1** | database-dictionary.md |
| 38 | Index governance | **P1** | INDEX-GOVERNANCE.md |
| 39 | Database governance | **P1** | DATABASE-GOVERNANCE.md |
| 40 | DR runbook | **P0** | dr-runbook.md |
| 41 | Zero-downtime migrations | **P1** | zero-downtime-migrations.md |
| 42 | Cache invalidation map | **P1** | cache-invalidation.md |
| 43 | Seed strategy 45K | **P1** | seed-data-45k.md |
| 44 | Testing strategy | **P0** | testing-strategy.md |
| 45 | Laravel architecture | **P1** | laravel-architecture.md |
| 46 | API conventions | **P1** | api-conventions.md |
| 47 | React/Inertia rules | **P1** | rules/react-inertia.mdc |
| 48 | Normalization 1NF–4NF + CQRS | **P0** | normalization-and-cqrs.md |
| 49 | Data quality rules | **P1** | data-quality-rules.md |
| 50 | ADRs (9 decisions) | **P1** | adr/*.md |
| 51 | Adaptive Database Governance | **P0** | DATABASE-ADAPTIVE-GOVERNANCE.md |
| 52 | Performance Budget | **P0** | PERFORMANCE-BUDGET.md |
| 53 | Data Lifecycle Matrix | **P1** | DATA-LIFECYCLE-MATRIX.md |
| 54 | Schema Change Impact Analysis | **P0** | schema-change-impact.md |
| 55 | Logical Data Architecture | **P1** | logical-data-architecture.md |
| 56 | Database Intelligence Layer | **P1** | DATABASE-INTELLIGENCE-LAYER.md |
| 57 | Expert Knowledge Base | **P1** | DATABASE-KNOWLEDGE-BASE.md |
| 58 | Optimization Learning | **P2** | DATABASE-OPTIMIZATION-LEARNING.md |
| 59 | Self-Healing Runbook | **P2** | SELF-HEALING-RUNBOOK.md |
| 60 | ADR-010 Intelligence Layer | **P1** | adr/ADR-010-intelligence-layer.md |

## Self-Assessment — v3.0 (Intelligence Layer)

| Layer | Score | Notes |
|-------|------:|-------|
| Database Architecture | 92 | 89 tables, blueprint + dictionary |
| Data Integrity | 94 | Constraints + governance |
| PostgreSQL Design | 91 | Partitioning, RLS, types |
| Indexing | 91 | Matrix + adaptive INDEX-GOVERNANCE |
| Partitioning | 92 | P0 from year 1 for 45K baseline |
| Performance Design | 91 | Batch, summary, MV + PERFORMANCE-BUDGET |
| Scalability 45K | 91 | Dynamic capacity model |
| Adaptive Governance | 92 | Feedback loop + measurement-based |
| **Intelligence Layer** | **88** | Expert KB + learning design — ops pending |
| **Self-Healing** | **85** | Runbook documented — not operational |
| Redis/Caching | 88 | + invalidation map |
| Reporting | 89 | MV candidates (not fixed count) |
| Security/RLS/Audit | 88 | RLS + policies doc |
| Backup/PITR | 80 | Config documented |
| Disaster Recovery | **88** | dr-runbook added |
| Monitoring | 82 | + intelligence layer requirements |
| Load/Stress Testing | **75** | Strategy + template (not executed) |
| Laravel Architecture | **82** | laravel-architecture.md |
| React/Inertia | **75** | react-inertia.mdc |
| Dev Governance / Cursor | 98 | Skills + rules + ADR-009/010 |
| Production Readiness | **85** | Checklists complete, not proven |

### **Overall: 91/100 — Adaptive Expert Database Architecture**

Path to **95+:** Operationalize Phases 2–3 (monitoring + rule engine), execute load tests, DR drill, populate optimization event log.

## Maturity Levels

| Level | Status |
|-------|--------|
| Architecture Ready | ✅ |
| Development Ready | ✅ |
| Production Proven | ⏳ Requires measured tests |

## Implementation Order by Module

```
Phase A — Foundation ✅
  1. organization (schools, branches)
  2. academic (years, terms, grade levels)
  3. security (roles, permissions) + RLS

Phase B — Core Academic ✅
  4. students + guardians
  5. enrollment (classes, sections, enrollments)
  6. curriculum (subjects, curricula)
  7. teachers + vocational

Phase C — Daily Operations ✅
  8. timetable.periods
  9. attendance (partitioned) + daily_section_summary
  10. materialized views (reports)

Phase D — Lifecycle ⏳
  11. admission
  12. exams + results
  13. promotion + transfers
  14. graduation + certificates

Phase E — Supporting ⏳
  15. finance
  16. communication + workflow
  17. documents + audit

Phase F — Production ⏳
  18. PgBouncer + Read Replica
  19. Load testing (500–2000 concurrent)
  20. Monitoring + alerts + PITR test
```

Track progress: [WORK-PLAN.md](./WORK-PLAN.md)

## Four Layers Summary

```
Layer 1 — Data Integrity (P0)
  Normalization, PK/FK, Constraints, Transactions, Audit, RLS

Layer 2 — Performance (P0)
  Partitioning (year 1), Indexes, Batch Writes, daily_summary, Query Optimization

Layer 3 — Scalability (P0 at launch for 45K)
  Redis, PgBouncer, Queues, Replicas, Materialized Views

Layer 4 — Resilience (P0)
  Backup, PITR, DR, Monitoring, Archiving, Load Testing
```

## Success Criteria — 45K Scenario

- [ ] 45,000 active students supported without degradation
- [ ] Morning attendance (45K writes in 30 min) completes reliably
- [ ] School dashboard P95 < 1s via daily_section_summary
- [ ] Directorate dashboard (20 schools) P95 < 3s via materialized views
- [ ] 10-year attendance query uses partition pruning (EXPLAIN verified)
- [ ] RLS isolates school data — verified by penetration test
- [ ] Load test at 900 concurrent teachers documented
- [ ] Backup restore to specific timestamp tested monthly
