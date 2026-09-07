# Phase 3.10.1 — Security Discovery (Read-Only)

**Date:** 2026-09-07  
**Purpose:** Baseline before closing five Phase 3.10 conditions

---

## Current School Authorization Flow

1. `auth:sanctum` on `/api/v1/students*` (`routes/api.php`)
2. `StudentPolicy` checks permission only via `DatabaseAuthorizationService` — **no school scope**
3. `security.user_roles.school_id` exists but is **always null** in `SecurityPermissionSeeder::assignRole()` (`database/seeders/SecurityPermissionSeeder.php:63`)
4. `SchoolContextMiddleware` resolves school from `user.school_id`, session, or `X-School-Id` header (`app/Security/Middleware/SchoolContextMiddleware.php`) but **does not enforce** presence
5. **No school ownership check** on student resources

**Gap:** Permission-only authorization; cross-school IDOR possible once two schools exist.

---

## Current Resource Ownership Flow

- `students.students` has **no `school_id` column** (`database/migrations/2026_09_05_100400_create_students_and_guardians_tables.php`)
- School association intended via `enrollment.enrollments.school_id` (blueprint) but Students API slice does not create enrollments
- `StudentRecord::$fillable` excludes `school_id`, `role`, permissions (`app/Infrastructure/Persistence/Eloquent/StudentRecord.php`)
- Repositories query all students without school filter (`app/Infrastructure/Persistence/Student/EloquentStudentManagementReadRepository.php`)

**Gap:** No server-side resource ownership for students.

---

## Current RLS Context Flow

1. `SchoolContextMiddleware` sets `app.current_school_id` via `set_config(..., false)` only when school resolved (`app/Security/Middleware/SchoolContextMiddleware.php:22`)
2. RLS on `enrollment.enrollments` and `attendance.records` (`database/migrations/2026_09_05_100900_enable_row_level_security.php`)
3. Policies include **NULL bypass**:

```sql
OR current_setting('app.current_school_id', true) IS NULL
OR current_setting('app.current_school_id', true) = ''
```

**Gap:** Missing context = unrestricted RLS access (fail-open).

---

## Current NULL-Context Behavior

| Layer | Behavior |
|-------|----------|
| Middleware | Continues request without school context |
| RLS | Allows all rows when context NULL/empty |
| Policy | Allows if permission granted (ignores school) |
| Repository | No school filter |

**Result:** Fail-open, not fail-closed.

---

## Current Composer Audit Behavior

- `composer test` runs `security:validate` but **not** `composer audit` (`composer.json:72-78`)
- `SecurityArchitectureValidator::validateDependencyAudit()` only warns (`app/Security/Validation/SecurityArchitectureValidator.php:135-143`)
- CI workflow runs `composer ci:check` → `composer test` (`.github/workflows/tests.yml:38`)

**Gap:** Dependency vulnerabilities do not block CI.

---

## Current Security Logging Behavior

- `SecurityAuditLogger` writes to log channel only (`app/Security/Audit/SecurityAuditLogger.php:35`)
- Blueprint has `audit.audit_logs` but **no `security_audit_logs` table**
- No PostgreSQL persistence for security events

**Gap:** No structured authoritative DB audit for security events.

---

## Current Mass-Assignment Exposure

| Location | Risk |
|----------|------|
| `StudentRecord::$fillable` | No sensitive fields listed — good |
| `CreateStudentRequest` / `UpdateStudentRequest` | No `prohibited` rules for `school_id`, `role`, etc. |
| `CreateStudentHandler` | Uses explicit DTO fields — good |
| `EloquentStudentRepository::saveNew` | Explicit field list — good |
| `SecurityPermissionSeeder` | Server-side only |

**Gap:** Client can send sensitive fields; Laravel strips unvalidated fields silently rather than rejecting (no explicit prohibition tests).

---

## Current Privilege Escalation Test Coverage

Existing: `tests/Feature/Security/StudentApiAuthorizationTest.php` — auth + permission only.

**Missing:**
- Cross-school IDOR (P1-P3, P7)
- Missing school context (E, F)
- RLS NULL fail-closed (G, H, I)
- Mass assignment injection (P5-P10)
- Vertical escalation (P4)
- DB audit persistence (J-N)

---

## Files Requiring Changes

| Condition | Files |
|-----------|-------|
| School IDOR/BOLA | `StudentPolicy`, new `SchoolScopeService`, `StudentSchoolAccessService`, `StudentRecord` + migration, repositories, queries/commands, `SecurityPermissionSeeder`, `InteractsWithSecurity` |
| RLS fail-closed | New migration fixing RLS policies, `SchoolContextMiddleware` terminate reset |
| Composer audit CI | `composer.json`, new `ComposerAuditGate`, `.cursor/security/COMPOSER-AUDIT-POLICY.json`, `SecurityArchitectureValidator` |
| Structured audit | Migration `security.security_audit_logs`, model, `SecurityAuditLogger`, tests |
| Mass assignment | FormRequests, validation rule/trait, escalation tests |
| Middleware | `RequireSchoolContextMiddleware`, `bootstrap/app.php`, `routes/api.php` |

---

## Production Autonomous Status

`AutonomousExecutionPolicy::isProductionAutonomousBlocked()` unchanged — production block when `app.env === production` (`app/Optimization/SelfHealing/AutonomousExecutionPolicy.php:138-145`).

**Must remain intact.**
