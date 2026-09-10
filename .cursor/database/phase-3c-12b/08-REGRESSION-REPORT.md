# Phase 3C.12B — Regression Report

## Commands (exclusive `sis_test`)

```text
php vendor/bin/phpunit -c phpunit.database-pgsql.xml \
  tests/Feature/Database/PostgreSql/Phase3C12GraduationSchemaTest.php \
  tests/Feature/Database/PostgreSql/StudentGradesPartitionAndIntegrityPostgreSqlTest.php

php vendor/bin/phpunit tests/Unit/Graduation/GraduationIdempotencyGuardTest.php
```

## Results

| Suite | Tests | Result |
|-------|------:|--------|
| Phase3C12GraduationSchemaTest | 5 | PASS |
| StudentGradesPartitionAndIntegrityPostgreSqlTest | 4 | PASS |
| GraduationIdempotencyGuardTest | 2 | PASS |
| **Total** | **11** | **PASS** |

Assertions (PG feature pair): 98.

## Contaminated run note

A simultaneous PHPUnit process against `sis_test` during an earlier attempt produced migrate:fresh races (`42P07` / missing `migrations`). Classified **I — Infrastructure/environment noise**. Re-run exclusive → PASS.

## Concurrency suite

11/11 PASS × 5 consecutive exclusive runs (see `04-CONCURRENCY-TEST-RESULTS.md`).

## Verdict

```text
REGRESSION: PASS
```

No Graduation schema or grades integrity regression introduced by 3C.12B tests.
