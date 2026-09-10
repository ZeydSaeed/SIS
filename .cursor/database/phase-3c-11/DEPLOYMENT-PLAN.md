# PHASE 3C.11 — DEPLOYMENT, SCALE & OBSERVABILITY PLAN

**Not executed.**

## Deployment sequence (future)

```text
Preflight (3C.10A, auth, backups, SchoolContext)
↓
Verify enrollments UNIQUE(id,school_id), audit tables, SchemaHelper
↓
M01 → M15 (tables)
↓
Constraint validation / pg_constraint checks
↓
M16 indexes
↓
M17 RLS verification (fail-closed tests)
↓
M18 triggers verification
↓
Application deploy (feature flagged off)
↓
Outbox staging smoke (non-prod first)
↓
StudentStatus consumer off until policy cleared
↓
Smoke + architecture/security gates
↓
Production gate (human)
```

## Zero-downtime / compatibility

| Phase | Notes |
|-------|-------|
| Expand | Add empty graduation tables — old app OK |
| Migrate | N/A data backfill initially |
| Verify | RLS/trigger tests on staging |
| Switch | Enable feature flags / routes |
| Contract | Event names governance before external consumers |

Zero-downtime **for empty schema expand** is feasible. Not guaranteed for later data backfills — no SLA invented.

## 20-year scale (ASSUMPTIONS from 3C.10 SCALE-MODEL)

| Growth | Concern | Action |
|--------|---------|--------|
| evidence_items / evaluations | highest | WATCHLIST partition; measure p95 |
| awards/completions | modest | none |
| outbox | general platform | existing retention |
| indexes | bloat | VACUUM monitor |

Archive ≠ hard-delete. Cold storage / detach later under retention HD if decided.

### Watch conditions (measure — no false precision)

row count · index size · write amp · query latency · bloat · vacuum · retention jobs

## Observability (integrate existing — no parallel framework)

| Signal | Future |
|--------|--------|
| Migration failures | deploy logs |
| Constraint / unique violations | PG logs + app exceptions |
| RLS denials | app/security logs if instrumented |
| Immutability trigger exceptions | PG + alert |
| Outbox attempts / lag | existing outbox monitors |
| Idempotency conflicts | handler metrics |
| Projection lag | ops query Graduated vs awards |
| Version conflicts | unique_violation rates |
| Query latency | EXPLAIN / APM existing |
| Growth | table size jobs |

---

## Partition watchlist (locked launch decision)

```text
NO PARTITION AT LAUNCH
```

Revisit evidence_items / requirement_evaluations when measured thresholds hit.
