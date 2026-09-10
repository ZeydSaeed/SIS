# DATABASE PHASE 2 REMEDIATION GATE

```text
DATABASE PHASE 2 REMEDIATION GATE
STATUS: PASS WITH CONDITIONS
```

**Date:** 2026-09-10  
**Scope:** R1–R10 safety + Admission PostgreSQL re-certification  
**Blueprint:** **87** (unchanged)

---

## 1. Status

```text
PASS WITH CONDITIONS
```

Conditions are limited to: (a) default CI remains sqlite (by design); (b) `architecture:validate --fitness` reports unrelated `SEC-DEP-001` composer audit noise; (c) enrollment/attendance still lack FORCE RLS (superuser bypass) — Admission now has FORCE + non-superuser actor tests.

---

## 2. Remediation Summary

| ID | Finding | Action | Status |
|----|---------|--------|--------|
| R1 | Protected DB guard | `ProtectedDatabaseGuard` + `config/sis.php` database safety | **DONE** |
| R2 | Destructive operations | TestCase hook + Artisan `CommandStarting` + `SchemaHelper::dropSchemas` | **DONE** |
| R3 | PostgreSQL test DB | `sis_test` + phpunit configs + ensure/recreate scripts | **DONE** |
| R4 | RLS tests | Admission PG suite with `sis_rls_tester` non-superuser role | **PASS** (5/5) |
| R5 | CHECK tests | Admission CHECK PG suite | **PASS** |
| R6 | sis regression guard | `ProtectedDatabaseRegressionTest` (no live wipe) | **PASS** |
| R7 | Config governance | Extended `config/sis.php` (no parallel framework) | **DONE** |
| R8 | CI safety | Default workflow stays sqlite; comments forbid `sis`; PG XML → `sis_test` | **DONE** |
| R9 | Live DB verification | Read-only `sis:verify-database` + catalog inspect | **PASS** |
| R10 | Migration integrity | Additive `2026_09_10_140000_phase2_remediation_force_admission_rls`; no history rewrite | **PASS** |

---

## 3. Files Changed

| File | Purpose | Why | Risk |
|------|---------|-----|------|
| `app/Database/ProtectedDatabaseGuard.php` | SSOT guard | Central fail-closed decisions | Low |
| `app/Database/Exceptions/ProtectedDatabaseException.php` | Exception type | Explicit block errors | Low |
| `config/sis.php` | Safety config | Env-driven allow/deny lists | Low |
| `app/Providers/AppServiceProvider.php` | Boot hooks | Artisan + prohibitDestructiveCommands | Low |
| `app/Database/SchemaHelper.php` | dropSchemas guard | Last-line CASCADE defense | Low |
| `tests/TestCase.php` | beforeRefreshingDatabase | Covers all RefreshDatabase tests | Low |
| `phpunit.database-pgsql.xml` | PG suite config | Point to `sis_test` not `sis` | Low |
| `phpunit.optimization-pgsql.xml` | Opt PG suite | Same | Low |
| `.env.example` | Docs | Safety env vars | Low |
| `.github/workflows/tests.yml` | CI comment | Document sqlite-only CI | Low |
| `scripts/ensure-sis-test-database.php` | Create sis_test | Disposable DB provisioning | Low |
| `scripts/recreate-sis-test-database.php` | Recreate sis_test | Clean PG test resets | Medium (only sis_test) |
| `tests/Support/Database/PostgreSqlIntegrationTestCase.php` | PG base | Multi-schema refresh + protect | Low |
| `tests/Support/Database/PostgreSqlRlsActor.php` | Non-superuser SET ROLE | Real RLS assertions | Low |
| `tests/Unit/Database/ProtectedDatabaseGuardTest.php` | Unit coverage | Guard matrix | Low |
| `tests/Unit/Database/ProtectedDatabaseRegressionTest.php` | Regression | sis never disposable | Low |
| `tests/Feature/Security/PostgreSql/AdmissionRlsPostgreSqlTest.php` | RLS integration | Cross-school + fail-closed | Low |
| `tests/Feature/Database/PostgreSql/AdmissionCheckConstraintPostgreSqlTest.php` | CHECK integration | Constraint enforcement | Low |
| `tests/Feature/Database/PostgreSqlFoundationVerificationTest.php` | Refuse protected DB | No seed against sis | Low |
| `tests/Feature/Database/Phase2AdmissionSchemaTest.php` | Remove duplicate skip | TestCase owns guard | Low |
| `tests/Feature/Security/AdmissionRlsFailClosedTest.php` | Remove duplicate skip | Same | Low |
| `database/migrations/2026_09_10_140000_phase2_remediation_force_admission_rls.php` | FORCE RLS admission | Owner subject to policies | Low |
| `docs/database/SIS-DATABASE-PHASE-2-REMEDIATION.md` | Policy doc | Operator guide | Low |
| `docs/database/06-RLS-SECURITY.md` | FORCE noted | Sync with live | Low |
| `docs/database/SIS-DATABASE-PHASE-2-REMEDIATION-GATE.md` | This gate | Re-certification | Low |

---

## 4. Tests

| Command | Result |
|---------|--------|
| `php artisan test --filter="ProtectedDatabase\|Phase2Admission\|AdmissionRlsFailClosed\|ApplicationStatusTest"` | **PASS** 11 · **SKIPPED** 3 (sqlite cannot assert PG catalog/RLS — not claimed as PASS) |
| `php artisan test -c phpunit.database-pgsql.xml --filter=Admission` | **PASS** 5/5 |
| `php artisan architecture:validate --fitness` | **PASS WITH NOISE** — fitness domains pass except `security_architecture` / `SEC-DEP-001` composer audit (unrelated dependency advisory) |
| `php artisan security:validate` | **PASS** |
| `php artisan sis:verify-database --no-seed-check` | **PASS** (57 business tables) |

---

## 5. PostgreSQL Verification (live `sis`, read-only)

| Item | Value |
|------|-------|
| Database | `sis` |
| Migrations Phase 2 | 131000, 131100, 131200, **140000 FORCE** — all Ran; **0 pending** |
| Admission tables | 3 present |
| Admission RLS | enabled |
| Admission FORCE RLS | **enabled** (remediation) |
| Admission policies | 3 |
| Admission CHECKs | 6 |
| `sis:verify-database` | PASS |

No `migrate:fresh` / wipe / DROP executed against `sis` during remediation validation.

---

## 6. Safety Verification

| Scenario | Expected | Proven by |
|----------|----------|-----------|
| testing → `:memory:` → ALLOWED | ALLOW | Default phpunit RefreshDatabase suite |
| testing → `sis_test` → ALLOWED | ALLOW | `phpunit.database-pgsql.xml` Admission suite |
| testing → `sis` → BLOCKED | BLOCK | `ProtectedDatabaseGuardTest` / Regression |
| unknown name → BLOCKED | BLOCK | `test_unknown_database_name_is_fail_closed` |
| `force_protected` → BLOCKED | BLOCK | Regression `SchemaHelper::dropSchemas` |
| protected flag / Artisan migrate:fresh on sis | BLOCK | AppServiceProvider + guard (not executed against live) |

---

## 7. Incident Closure

```text
Previous issue:
RefreshDatabase reached sis and damaged migration catalog (twice).

Repair:
Migration rows restored for existing objects; missing migrations re-applied;
catalog re-verified (completed in prior Phase 2 gate work).

Root cause:
PHPUnit PG configs and shell env could target DB_DATABASE=sis;
RefreshDatabase had no global protected-name guard;
only two Admission tests skipped (insufficient).

Preventive control:
ProtectedDatabaseGuard + TestCase + Artisan + SchemaHelper;
phpunit.*.pgsql.xml → sis_test; CI remains sqlite :memory:.

Regression test:
Tests\Unit\Database\ProtectedDatabaseRegressionTest

Current status:
CLOSED
```

---

## 8. Remaining Conditions

1. Default CI does not run PostgreSQL Admission suite (sqlite-only) — operators must run `phpunit.database-pgsql.xml` locally/CI job when PG proof required.  
2. `architecture:validate --fitness` may fail on unrelated Composer audit `SEC-DEP-001`.  
3. Enrollment/attendance RLS still without FORCE (superuser bypass) — out of Phase 2 remediation scope; Admission is hardened.

---

## 9. Blueprint

```text
Blueprint = 87
```

No table-count change. Additive FORCE RLS migration only.

---

## 10. Phase 3 Recommendation

```text
PHASE 3 RECOMMENDATION:
READY FOR HUMAN APPROVAL
```

Suggested next human choice remains: Student RLS expansion **or** Assessment/Exams (D5) — not started.

---

```text
HUMAN APPROVAL REQUIRED

No Phase 3 implementation was started.
No Exams/Grades implementation was started.
No ERP implementation was started.
```
