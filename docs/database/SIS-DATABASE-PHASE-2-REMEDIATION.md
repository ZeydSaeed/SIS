# Phase 2 Remediation — Protected Database & Test Safety

**Date:** 2026-09-10  
**Scope:** Safety + test infrastructure only (no Admission/Exams/ERP expansion)

## Policy

| Database | Destructive tests (`RefreshDatabase`, `migrate:fresh`, `db:wipe`, schema CASCADE drop) |
|----------|----------------------------------------------------------------------------------------|
| `sis` | **BLOCKED** (protected) |
| `sis_test` | **ALLOWED** (disposable PostgreSQL) |
| SQLite `:memory:` | **ALLOWED** (default CI/phpunit.xml) |
| Unknown PG name | **BLOCKED** (fail-closed allowlist) |

## Configuration (`config/sis.php`)

- `sis.database.protected_names` ← `SIS_PROTECTED_DATABASES` (default `sis`)
- `sis.database.destructive_allowed_names` ← `SIS_DESTRUCTIVE_ALLOWED_DATABASES` (default `sis_test,:memory:`)
- `sis.database.force_protected` ← emergency kill-switch
- `sis.database.pgsql_test_database` ← `sis_test`

## Enforcement points

1. `App\Database\ProtectedDatabaseGuard` — SSOT  
2. `tests/TestCase::beforeRefreshingDatabase()` — all RefreshDatabase consumers  
3. `AppServiceProvider` — `DB::prohibitDestructiveCommands` when protected + `CommandStarting` for migrate:fresh/wipe/reset  
4. `SchemaHelper::dropSchemas()` — asserts guard before CASCADE  

## PostgreSQL test suite

```bash
php scripts/ensure-sis-test-database.php
# or recreate disposable DB:
php scripts/recreate-sis-test-database.php

php artisan test -c phpunit.database-pgsql.xml --filter=Admission
```

`phpunit.database-pgsql.xml` and `phpunit.optimization-pgsql.xml` must use `DB_DATABASE=sis_test` — **never** `sis`.

## Incident (closed)

RefreshDatabase previously targeted live `sis` and damaged the migrations catalog. Preventive controls above make that path fail-closed. Regression: `ProtectedDatabaseRegressionTest`.
