# PHASE 3C.11A — MIGRATION SAFETY AUDIT

**Migrations NOT created. Audit of planned M01–M18 only.**

## Graph validity

| Check | Result |
|-------|--------|
| Impossible ordering | None found |
| Policy → requirement → completion → evidence → approval → award → lineage | PASS |
| Award version after approval + completion version | PASS |
| Supersession after versions | PASS |
| Revocation after award versions | PASS |
| Indexes after tables | PASS |
| Triggers after tables | PASS |
| Missing prereq `enrollments(id,school_id)` unique | Documented LIVE — verify preflight |
| SchemaHelper graduation | LIVE — M01 idempotent IF NOT EXISTS |

## Circular dependency handling

| Pair | Plan | Verdict |
|------|------|---------|
| completion_outcomes.current_official_version_id ↔ versions | Create outcomes without FK; add FK after M07 | **PASS** — explicit |
| graduation_awards ↔ award_versions pointer | Same pattern expected | **PASS** if mirrored (confirm in impl ticket) |
| Supersession self-FK | Nullable self-refs + edge table | PASS |
| Revocation → award_version | After M13 | PASS |

## Per-migration risk (summary)

| M | Risk | Rollback if empty | Notes |
|---|------|-------------------|-------|
| M01 | LOW | no-op drop schema | Idempotent |
| M02–M05 | LOW | DROP TABLE | Catalog |
| M06–M07 | MED | DROP if empty | Deferred pointer FK |
| M08–M10 | LOW–MED | DROP if empty | Evidence/eval |
| M11–M15 | MED | DROP if empty | Approval/award/lineage |
| M16 | LOW–MED | DROP INDEX | Write amp |
| M17 | **HIGH** | DROP POLICY only w/ approval; DISABLE forbidden as auto | **Security window — see RLS audit** |
| M18 | MED | DROP TRIGGER | Denorm + immutability |

## Failure behavior

- Forward migration failure: stop deploy; do not continue to app switch.  
- Partial M02–M15 without M17: **unsafe residual state** → F-11A-001.  
- Transaction: Laravel migrations typically one file per transaction on PG — RLS-in-same-file (Option A) reduces window to zero within that table’s migration.

## Duplicate ownership

No second outbox/idempotency/schema ownership. Stale blueprint tables not in graph. **PASS**

## Hidden dependency

Optional composite `(enrollment_id, academic_year_id)` — LIVE exists; plan notes optional. Not hidden blocker.
