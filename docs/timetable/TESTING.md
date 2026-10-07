# Timetable — Testing

## Unit (pure Domain, `php vendor/bin/phpunit tests/Unit/Domain`)

| File | Covers |
|------|--------|
| `TimetableBuilderServicesTest` | auto-place, audit, shift (unchanged behaviour on the default settings) |
| `TimetableAnalysisServicesTest` | advisor ready / blocked, workload, quality grades, day runs |
| `TimetableSolverTest` | spec §96 scenarios: whole school without configuration (5 sections × 30 lessons), groups of one division sharing a slot + joined classes, co-teacher sessions (3 of 5), unavailable teacher + closed workshop, capacity shortage (blocked with reason), locked lessons counted, impossible load explained, repair after absence (moves only what broke), rule precedence + origin, **§147 vocational scenario** (theory-before-practice CRITICAL, 18 + 18 split, workshop exclusivity, lab room) — each verified by the independent auditor |
| `TimetableEngineServicesTest` | substitute ranking, version comparison, group split, student filter, effective version, fingerprint, advisor engine findings, move / swap suggestions, rule catalogue validation, settings |

## PostgreSQL (`php -d memory_limit=2G vendor/bin/phpunit -c phpunit.database-pgsql.xml --filter Timetable`)

`TimetableEnginePostgreSqlTest`:
1. **data → published**: settings, activities from curriculum, split, availability, rules (and a refused scope),
   page props, queued generation (sync), review partial reload, apply, lock (manual move refused), version,
   submit (workflow request), decide refused for the manager, approved by a separate `timetable_approver`,
   publish, immutable entries, effective API for a date, student API, restore after a manual change, CSV export.
2. **gates**: advisor BLOCKED (no double slot) with blockers flashed, what-if run not appliable, stale run refused,
   one active run per school-year.
3. **authorization + idempotency**: same key twice → one version; no permission → 403 on runs / rules / settings.
4. **RLS + no hard delete**: another school sees none of the rules / activities (non-superuser role); DELETE rejected.

Existing timetable / schedule / period / attendance / workflow suites keep passing (178 / 179 on the related
filter; the one failure is the pre-existing outdated `transfers_tables_remain_absent`). Full suite 554 / 558, the
4 failures pre-existing and unrelated.

## Stress (scratch script, Windows dev box, PHP 8.4, 10 subjects / section, 2 practicals as doubles)

| Sections | Teachers | Blocks | Solve | Unplaced | Hard | Notes |
|----------|----------|--------|-------|----------|------|-------|
| 30 | 60 | 840 | 5.6 s | 0 | 0 | no section gaps |
| 60 | 120 | 1 680 | 9.8 s | 0 | 0 | |
| 90 | 180 | 2 520 | 18 s | 0 | 0 | ≈ 2× the 45K baseline school |
| 60 (overloaded teachers by construction) | 102 | 1 680 | 30 s | 10 | 0 | infeasible input — reported with reasons |
