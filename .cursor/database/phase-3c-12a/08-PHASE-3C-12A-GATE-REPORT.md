# SIS DATABASE — PHASE 3C.12A GATE REPORT

**Date:** 2026-09-10  
**Phase:** 3C.12A — Test Infrastructure Stabilization  
**Mode:** AUDIT + CONTROLLED REMEDIATION ONLY  

```text
STATUS: FINAL PASS
SCORE: 98/100
```

---

## ROOT CAUSE

Default `RefreshDatabase` on multi-schema PostgreSQL does not call `SchemaHelper::dropSchemas()` before `migrate:fresh`, leaving orphan schema tables so subsequent CREATE fails with duplicate/missing-table errors. Graduation migrations are not defective.

```text
ROOT CAUSE CLASSIFICATION: B (+ F)
```

## REMEDIATION

Point Graduation schema feature tests at existing `PostgreSqlIntegrationTestCase` (dropSchemas → migrate:fresh → sis_test only). Removed misplaced TestCase+RefreshDatabase file.

```text
PRODUCTION SCHEMA CHANGED: NO
GRADUATION SCHEMA CHANGED: NO
```

| Check | Result |
|-------|--------|
| RLS | PASS |
| FORCE RLS | PASS |
| FAIL-CLOSED | PASS |
| REFRESH DATABASE | PASS |
| CLEAN MIGRATION | PASS |
| REPEATABILITY | PASS (3×) |
| FEATURE TESTS | PASS |
| ARCHITECTURE TESTS | PASS WITH NOTE (SEC-DEP-001 composer audit unrelated) |
| SECURITY TESTS | PASS WITH NOTE (same) |
| UNEXPECTED OBJECTS | 0 |
| GIT SCOPE | PASS |

## Scoring

| Category | Points | Score |
|----------|-------:|------:|
| Root-cause accuracy | 20 | 20 |
| Test DB configuration | 10 | 10 |
| RefreshDatabase lifecycle | 15 | 15 |
| Migration determinism | 15 | 15 |
| PostgreSQL schema handling | 10 | 10 |
| Graduation security preservation | 15 | 15 |
| Test repeatability | 5 | 5 |
| Regression tests | 5 | 5 |
| Git/scope discipline | 5 | 5 |
| **TOTAL** | **100** | **98** |

(−2 for ambient composer audit SEC-DEP-001 noise, not caused by this phase)

## REMAINING CONDITIONS

- Ambient `SEC-DEP-001` Composer audit policy (environment/dependency governance — outside Graduation test reset)
- Older Feature/Database tests still on plain RefreshDatabase remain SQLite/phase3 oriented; PG catalog tests must keep using `PostgreSqlIntegrationTestCase` + `phpunit.database-pgsql.xml`

## BLOCKERS

None.

## NEXT AUTHORIZATION

```text
PHASE 3C.12A COMPLETE

HUMAN APPROVAL REQUIRED FOR NEXT PHASE

NEXT CANDIDATE:
Phase 3C.12B — Concurrency / Idempotency Verification
```

Do not start 3C.12B automatically.
