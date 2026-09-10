# 02 — RLS MIGRATION MATRIX (Option A Binding)

**DDL NOT EXECUTED.** App access before RLS column must be **NO**.

M17 from 3C.11 = superseded as the place where RLS is first applied. Renumbered logical units below keep table dependency order; each **table-creating** unit embeds Option A.

| Migration (logical) | Table | school_id | RLS same migration | FORCE | Policy same migration | App access before RLS |
| ------------------- | ----- | --------- | ------------------ | ----- | --------------------- | --------------------- |
| M01 | _(schema only)_ | N/A | N/A | N/A | N/A | N/A |
| M02 | eligibility_policies | YES | YES | YES | YES | **NO** |
| M03 | eligibility_policy_versions | YES | YES | YES | YES | **NO** |
| M04 | requirement_definitions | YES | YES | YES | YES | **NO** |
| M05 | requirement_definition_versions | YES | YES | YES | YES | **NO** |
| M06 | completion_outcomes | YES | YES | YES | YES | **NO** |
| M07 | completion_outcome_versions | YES | YES | YES | YES | **NO** |
| M08 | evidence_sets | YES | YES | YES | YES | **NO** |
| M09 | evidence_items | YES | YES | YES | YES | **NO** |
| M10 | requirement_evaluations | YES | YES | YES | YES | **NO** |
| M11 | graduation_approvals | YES | YES | YES | YES | **NO** |
| M12 | graduation_awards | YES | YES | YES | YES | **NO** |
| M13 | graduation_award_versions | YES | YES | YES | YES | **NO** |
| M14 | outcome_supersessions | YES | YES | YES | YES | **NO** |
| M15 | revocation_records | YES | YES | YES | YES | **NO** |
| M16 | supporting indexes only | N/A | N/A (no new tenant table) | N/A | N/A | N/A |
| M17′ | RLS **verification** only (optional) | — | Assert policies exist | Assert FORCE | Assert fail-closed | N/A |
| M18 | immutability + denorm triggers | — | N/A | N/A | N/A | N/A |

### Deferred pointer FK

`completion_outcomes.current_official_version_id` FK added in M07 (or follow-on ALTER inside M07 after versions exist) — still inside migrations that already applied Option A to both tables.

### Enforceability

| Control | Mechanism |
|---------|-----------|
| Same-migration RLS | Mandatory in implementation checklist / human auth record |
| Transaction | Laravel pgsql migration transaction |
| No app before protected commit | No Graduation HTTP/routes until migrations green |
| CI (future) | Schema test: every `graduation.*` school-scoped table has `relrowsecurity` + `relforcerowsecurity` |

```text
App access before RLS: NO for all tenant-scoped Graduation tables
```
