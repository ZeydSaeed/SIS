# 02 — REFRESH DATABASE TRACE

## Broken path (pre-remediation)

```text
Phase3C12GraduationSchemaTest
  ↓ TestCase + RefreshDatabase (default)
  ↓ beforeRefreshingDatabase → ProtectedDatabaseGuard
  ↓ Laravel refreshTestDatabase → migrate:fresh
  ↓ drops tables visible to default dropper (incomplete for multi-schema)
  ↓ orphan tables remain in organization/academic/…/graduation schemas
  ↓ migrate re-runs CREATE → duplicate table / missing FK parent
```

## Correct project path (established)

```text
PostgreSqlIntegrationTestCase
  ↓ require pgsql + DB_DATABASE=sis_test
  ↓ beforeRefreshingDatabase → ProtectedDatabaseGuard
  ↓ SchemaHelper::dropSchemas()   ← critical
  ↓ migrate:fresh
  ↓ RefreshDatabaseState::$migrated = true
  ↓ beginDatabaseTransaction
```

Evidence: `tests/Support/Database/PostgreSqlIntegrationTestCase.php` (used by grades/admission PG tests).
