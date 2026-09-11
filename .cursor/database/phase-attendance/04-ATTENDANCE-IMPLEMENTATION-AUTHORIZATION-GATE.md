# SIS DATABASE — PHASE R1  
# ATTENDANCE APPLICATION  
# R1.3 — IMPLEMENTATION AUTHORIZATION GATE

**Date:** 2026-09-11  
**Stage:** IMPLEMENTATION AUTHORIZATION  
**Mode:** AUDIT + AUTHORIZATION PREPARATION ONLY  

```text
IMPLEMENTATION AUTHORIZATION: NOT YET GRANTED
PERMISSION/CONFIG MUTATION: NOT GRANTED
DATABASE / DDL / RLS MUTATION: NOT GRANTED
CQRS IMPLEMENTATION: NOT GRANTED
```

**Inputs:**  
`01-ATTENDANCE-APPLICATION-READINESS-GATE.md`  
`02-ATTENDANCE-APPLICATION-DESIGN-LOCK.md`  
`03-ATTENDANCE-ROLE-PERMISSION-APPROVAL-GATE.md`  
Repository: `config/security.php`, `Permission.php`, `SecurityPermissionSeeder.php`, policies, SchoolContext

---

## 1. ATT-D3 human approval verification

### Prior project-file state (insufficient alone)

| Artifact | ATT-D3 state |
|----------|--------------|
| R1.1 Design Lock | Role map = HUMAN CONDITION |
| R1.2 Approval Gate | `BLOCKED — HUMAN DECISION REQUIRED`; Option B = **RECOMMENDED only** |

R1.2 recommendation / assistant preference is **not** human approval.

### Explicit human approval (this stage input)

The human directive for Phase R1.3 §1 states:

```text
The human-approved decision for ATT-D3 is:

OPTION B — DEDICATED ATTENDANCE ROLES
```

With the exact mapping and SoD rules listed in §2 below.

```text
ATT-D3 STATUS (recorded here):
APPROVED — OPTION B

Approval authority: Human directive in R1.3 authorization-gate request
Approval date: 2026-09-11
Prior R1.2 file status: superseded for ATT-D3 decision only
```

**Note:** Approving Option B **does not** authorize mutating `config/security.php`, seeders, tests, or CQRS. That requires a **separate** human implementation-authorization phrase after this package.

---

## 2. Approved ATT-D3 mapping (locked for authorization package)

### Roles → permissions

| Role | Permissions |
|------|-------------|
| `attendance_viewer` | `attendance.view` |
| `attendance_teacher` | `attendance.view`, `attendance.session.create`, `attendance.mark`, `attendance.session.close` |
| `attendance_manager` | `attendance.view`, `attendance.session.create`, `attendance.mark`, `attendance.correct`, `attendance.session.close` |

### SoD (locked)

```text
attendance.mark ≠ attendance.correct
Marker may close.
Corrector may close.
No Attendance-specific god permission (attendance.administer = REJECTED).
```

---

## 3. Dependency check (existing security architecture)

| Dependency | Compatible? | Evidence |
|------------|-------------|----------|
| Five permission codes in `Permission.php` | **YES** | Same pattern as `GRADES_*` / `ENROLLMENT_*` |
| Registry in `config/security.php` `'permissions'` | **YES** | Dotted `{module}.{action}` entries |
| Three roles in `config/security.php` `'roles'` | **YES** | Parallel to `grades_teacher` / `grades_manager` / `grades_viewer` |
| `SecurityPermissionSeeder` materialization | **YES** | Iterates config permissions + roles → DB |
| Grant helpers on seeder | **YES** | Pattern: `grantGradesTeacher`, etc. |
| `user_roles.school_id` binding | **YES** | `assignRole(..., ?int $schoolId)` |
| `DatabaseAuthorizationService` | **YES** | Union of role permissions; no school in permission code |
| SchoolContext / ATT-D5 | **YES** | Middleware sets `app.current_school_id`; Design Lock tenant resolve |
| Permission ≠ cross-school | **YES** | GradePolicy = permission ∧ school access; Attendance must mirror |
| Architectural change required? | **NO** | |

```text
BLOCKING DEPENDENCY: NONE
```

---

## 4. Authorization scope (what a future implement grant may cover)

### IN SCOPE (candidates for next human IMPLEMENT grant)

| Item | Detail |
|------|--------|
| A. Permission definitions | Five locked codes only |
| B. Role definitions | `attendance_viewer`, `attendance_teacher`, `attendance_manager` |
| C. Role → permission grants | Exact Option B matrix |
| D. Seeder | Config-driven sync + `grantAttendanceViewer/Teacher/Manager` helpers |
| E. Authorization tests | Grant/deny + school-scope denial (security only) |
| F. AttendancePolicy | **CONDITIONAL** — only if/when a Laravel Policy is needed for authorize(); may wait until HTTP stage; if added early, permission ∧ school access only |

### OUT OF SCOPE (remain unauthorized)

```text
attendance.administer · attendance.session.reopen · attendance.session.cancel
Cross-school / directorate Attendance via permission
RLS bypass / FORCE RLS / ATT-SEC-*
Attendance schema / migrations / DDL / session.school_id
Attendance CQRS / Domain / Application handlers
Controllers / routes / HTTP
AttendanceBatchService migration
Platform-admin / security.manage_users changes
R1.4 CQRS · R1.5 HTTP
```

---

## 5. Ordered implementation plan (DO NOT EXECUTE)

| # | Step | File(s) | Expected mutation | Reason | Risk | Test/gate | Rollback |
|---|------|---------|-------------------|--------|------|-----------|----------|
| 1 | Add five `Permission` constants + `all()` | `app/Security/Authorization/Permission.php` | Constants only | Compile-time SSOT | Low | Unit permission list | Revert constants |
| 2 | Register five permission descriptions | `config/security.php` `'permissions'` | Five entries | Seeder source | Low | Seeder dry-run / feature | Remove entries |
| 3 | Add three roles + exact grants | `config/security.php` `'roles'` | Three role arrays | Option B | Medium (mis-grant) | Auth tests | Remove roles |
| 4 | Seeder helpers | `SecurityPermissionSeeder.php` | `grantAttendance*` | Test/bootstrap parity with grades | Low | Feature auth tests | Remove helpers |
| 5 | Optional early `AttendancePolicy` | `app/Security/Policies/AttendancePolicy.php` + provider register | viewAny/view/create/mark/correct/close | Mirror GradePolicy | Medium if premature without model | Policy unit tests | Delete policy |
| 6 | Authorization tests | `tests/Unit/Security/*` and/or Feature Security | Grant matrix + deny correct for teacher + cross-school deny | Acceptance §7 | Low | PHPUnit | Delete tests |
| 7 | Seed / verify DB materialization | Runtime via seeder in tests | `security.permissions` / `roles` / `role_permissions` | Materialize grants | Low | Tests | Re-seed prior config |
| 8 | Security validation | `php artisan` security validators if used in CI | None new | Governance | Low | `security:validate` if applicable | N/A |

**Not in this plan:** CQRS, routes, DDL, RLS, batch service migration.

---

## 6. Authorization matrix

| Implementation Item | Authorized now? | Scope if later authorized |
|---------------------|-----------------|---------------------------|
| Permission constants | **NO** (await implement grant) | Five locked permissions only |
| Permission registry | **NO** | Five locked permissions only |
| `attendance_viewer` | **NO** | Approved mapping |
| `attendance_teacher` | **NO** | Approved mapping |
| `attendance_manager` | **NO** | Approved mapping |
| Seeder changes | **NO** | Approved grants + helpers only |
| Authorization tests | **NO** | Security only |
| Attendance Policy | **NO** | Only if required; permission ∧ school |
| CQRS | **NO** | Separate stage R1.4 |
| DDL | **NO** | Separate stage |
| RLS | **NO** | Separate stage |
| Routes | **NO** | Separate stage R1.5 |
| Controllers | **NO** | Separate stage R1.5 |
| `attendance.administer` | **NO** | Permanently rejected |
| reopen | **NO** | Deferred |
| cancel | **NO** | Deferred |

```text
ATT-D3 mapping approval ≠ permission/config mutation authorization
≠ CQRS authorization
```

---

## 7. Security acceptance criteria (must hold after authorized implement)

1. `attendance_viewer` reads Attendance only within authorized school scope.  
2. `attendance_teacher` may view, create sessions, mark, close.  
3. `attendance_teacher` must **not** correct, cross school, reopen, or cancel.  
4. `attendance_manager` may view, create, mark, correct, close.  
5. `attendance_manager` must **not** cross school, reopen, or cancel.  
6. No role receives `attendance.administer`.  
7. Permission presence alone never grants cross-school access.  
8. SchoolContext remains mandatory (ATT-D5).

---

## 8. Suggested human implement-authorization phrase (for later)

When the human is ready to allow mutation of security config only, use an explicit phrase such as:

```text
APPROVED — IMPLEMENT ATTENDANCE R1 SECURITY PACKAGE ONLY
(Option B permissions + roles + seeder helpers + authorization tests)
CQRS / HTTP / DDL / RLS: NOT AUTHORIZED
```

Until that (or equivalent) is issued:

```text
NO FILE MUTATION IS PERMITTED.
```

---

## 9. Phase control

```text
PHASE R1 — ATTENDANCE APPLICATION
STAGE R1.3 — IMPLEMENTATION AUTHORIZATION GATE

NOT R1.4 CQRS Implementation
NOT R1.5 HTTP/API Exposure

DO NOT ADVANCE AUTOMATICALLY.
```

---

## 10. Final decision

```text
SIS DATABASE — PHASE R1
ATTENDANCE APPLICATION
R1.3 — IMPLEMENTATION AUTHORIZATION GATE

ATT-D3:
  APPROVED — OPTION B (human directive recorded in this document)

Implementation package:
  READY FOR HUMAN IMPLEMENTATION AUTHORIZATION

Meaning:
  The security-only package is complete and architecturally compatible.
  A further explicit human grant is still required before any mutation.

IMPLEMENTATION AUTHORIZATION:
  NOT GRANTED

PERMISSION/CONFIG MUTATION:
  NOT GRANTED

DATABASE MUTATION:
  NOT GRANTED

RLS MUTATION:
  NOT GRANTED

CQRS IMPLEMENTATION:
  NOT GRANTED

NEXT HUMAN ACTION:
  Issue explicit IMPLEMENT grant for the R1 security package
  (permissions + roles + seeder + auth tests only)
  — or decline / amend scope —

STOP.
```

---

## Mutation check

```text
Files modified: ONLY
.cursor/database/phase-attendance/04-ATTENDANCE-IMPLEMENTATION-AUTHORIZATION-GATE.md

config · Permission.php · seeders · tests · PHP app · DDL · RLS: NONE
```
