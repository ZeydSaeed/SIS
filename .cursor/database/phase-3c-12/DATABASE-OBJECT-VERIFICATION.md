# DATABASE OBJECT VERIFICATION

**Source:** LIVE `sis` via pg_catalog (2026-09-10)

## Tables (14)

All present under `graduation.*` as listed in IMPLEMENTATION-REPORT.  
STALE `eligibility_rules` / `records`: **0**.

## RLS

Every tenant table: `relrowsecurity=1`, `relforcerowsecurity=1`.

## Unexpected objects

| Kind | Count | Notes |
|------|------:|-------|
| Unexpected graduation tables | 0 | |
| Unexpected stale blueprint tables | 0 | |

SchemaHelper already listed `graduation` prior to this phase.
