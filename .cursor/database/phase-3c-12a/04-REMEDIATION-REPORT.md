# 04 — REMEDIATION REPORT

## Option selected

**Option 1 — Fix test database lifecycle** (preferred)

## Changes

| Change | Detail |
|--------|--------|
| Move/replace test | `tests/Feature/Database/PostgreSql/Phase3C12GraduationSchemaTest.php` |
| Base class | `PostgreSqlIntegrationTestCase` (dropSchemas + migrate:fresh) |
| Delete | Misplaced `tests/Feature/Database/Phase3C12GraduationSchemaTest.php` |
| Suite | Covered by existing `phpunit.database-pgsql.xml` directory include |
| Assert | `getDatabaseName() === sis_test` |

## Not changed

- No Graduation production migrations
- No RLS/triggers/constraints
- No SchemaHelper production semantics
- No CQRS / StudentStatus / business policy
- No SQLite substitution

## Clean vs stale comparison

| Path | Result |
|------|--------|
| Stale sis_test + plain RefreshDatabase | FAIL (duplicate/missing) |
| sis_test + PostgreSqlIntegrationTestCase | PASS (5/5) × 3 runs |
