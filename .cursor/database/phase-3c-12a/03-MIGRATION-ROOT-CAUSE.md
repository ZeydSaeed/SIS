# 03 — MIGRATION ROOT CAUSE

```text
ROOT CAUSE
==========
Observed failure:
  RefreshDatabase on sis_test → SQLSTATE[42P07] duplicate table
  (jobs, roles, monitoring_snapshots, students, …)
  and/or SQLSTATE[42P01] missing parent (organization.schools, …)

First failing migration:
  Early foundation migrations during migrate:fresh recreate
  (not Graduation-specific CREATE)

PostgreSQL error:
  42P07 duplicate_table / 42P01 undefined_table

Existing object:
  Tables left in non-public SIS schemas after incomplete wipe

Expected state:
  Empty schemas or full drop before migrate:fresh

Actual state:
  Orphan schema-qualified relations survived Laravel migrate:fresh

Why the object exists/missing:
  Default RefreshDatabase does not call SchemaHelper::dropSchemas()
  Multi-schema SIS architecture leaves schema tables behind

Why RefreshDatabase produced this state:
  Test used TestCase+RefreshDatabase instead of PostgreSqlIntegrationTestCase

Production schema affected:
  NO

Graduation schema defect:
  NO — NOT PROVEN (LIVE sis catalog healthy; defect is test lifecycle)
```

## Classification

```text
B. incorrect reset lifecycle
(+ F. test bootstrap defect — wrong base class / suite wiring)
```

Not: I (production migration defect).
