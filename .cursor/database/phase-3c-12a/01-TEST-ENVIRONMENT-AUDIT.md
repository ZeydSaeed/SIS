# 01 — TEST ENVIRONMENT AUDIT

**Phase:** 3C.12A  
**Date:** 2026-09-10  

## Configuration evidence

| Source | DB_CONNECTION | DB_DATABASE | Notes |
|--------|---------------|-------------|-------|
| `phpunit.xml` (default) | sqlite | `:memory:` | Unit/default feature |
| `phpunit.database-pgsql.xml` | pgsql | **sis_test** | Disposable PG suite |
| `phpunit.phase3.xml` | sqlite | `:memory:` | Not for RLS catalog |
| Default artisan `.env` | pgsql | **sis** (protected) | Must not RefreshDatabase |

`phpunit.database-pgsql.xml` also sets:

- `SIS_PROTECTED_DATABASES=sis`
- `SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_test,:memory:`

## Confirmation

Graduation feature tests after remediation assert:

```text
DB::connection()->getDatabaseName() === sis_test
```

Default tinker/artisan without phpunit env targets `sis` — production protected by `ProtectedDatabaseGuard` via `beforeRefreshingDatabase()`.
