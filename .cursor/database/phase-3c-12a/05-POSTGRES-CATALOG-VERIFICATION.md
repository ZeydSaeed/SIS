# 05 — POSTGRES CATALOG VERIFICATION

## LIVE `sis` (read-only)

| Check | Result |
|-------|--------|
| Database | sis |
| graduation table count | **14** |
| RLS+FORCE count | **14** |
| Unexpected graduation tables | **0** |

Production `sis` not modified by Phase 3C.12A.

## `sis_test` after successful RefreshDatabase path

Verified indirectly by feature assertions:

- all 14 tables exist
- RLS + FORCE on each
- fail-closed policies named `*_school_isolation`
- reject_delete triggers on history tables
- stale `eligibility_rules` / `records` absent
