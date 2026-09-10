# Phase 3C.12B — Tenant / RLS Concurrency Results

## Fixtures

Seed helper creates **School A** and **School B** with distinct enrollments (`SeedsGraduationConcurrencyGraph`).

## Tests

| Check | Result | Level |
|-------|--------|-------|
| Composite FK: school A + enrollment B rejected | PASS | DATABASE |
| Catalog: 14 graduation tables RLS + FORCE | PASS (`sis_test` + LIVE `sis`) | DATABASE |
| Fail-closed without `app.current_school_id` | PASS (Phase3C12 schema test) | RLS policy |
| Concurrent School A vs School B inserts (same logical enrollment) | N/A — enrollment IDs differ; composite FK prevents mismatched pairs | — |
| Non-superuser role concurrent RLS denial | **NOT EXECUTED** | — |

## Superuser caveat

Test role `postgres` **bypasses RLS**. FORCE RLS still applies to table owners that are non-bypass roles; catalog proves FORCE=on. Runtime “School A cannot read School B rows under app role” was previously validated in schema fail-closed patterns but **not** re-proven under parallel non-superuser sessions in 3C.12B.

## Fail closed expectation

Unauthorized cross-tenant **writes** via mismatched `(enrollment_id, school_id)` fail at composite FK / denorm triggers regardless of RLS bypass.

## Verdict

```text
TENANT/RLS: PARTIAL
```

No cross-school mutation demonstrated. Catalog FORCE intact. Concurrent non-superuser RLS session matrix remains a condition.

### F-12B-004 — Non-superuser concurrent RLS matrix deferred

| Field | Value |
|-------|-------|
| Class | G / I |
| Detected | Tests run as `postgres` |
| Evidence | PostgreSQL table-owner bypass; phpunit env `DB_USERNAME=postgres` |
| Impact | Concurrent policy enforcement under app role not evidenced here |
| Production relevance | FORCE RLS remains on LIVE; app must set `app.current_school_id` |
| Recommended action | Optional dedicated non-bypass test role in a later phase |
| Requires implementation approval | YES (for role/fixture work) |
