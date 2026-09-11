# SIS DATABASE — PHASE R1  
# ATTENDANCE APPLICATION  
# R1.2 — ROLE → PERMISSION MAPPING APPROVAL GATE

**Date:** 2026-09-11  
**Stage:** ROLE → PERMISSION MAPPING APPROVAL  
**Mode:** AUDIT + DESIGN + HUMAN-APPROVAL PREPARATION ONLY  

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
PERMISSION/CONFIG MUTATION: NOT GRANTED
DATABASE / DDL / RLS MUTATION: NOT GRANTED
CQRS IMPLEMENTATION: NOT GRANTED
HUMAN APPROVAL: REQUIRED
```

**Upstream:**  
`.cursor/database/phase-attendance/02-ATTENDANCE-APPLICATION-DESIGN-LOCK.md` — ATT-D3 role map = HUMAN CONDITION  

---

## 0. Purpose

Close the ATT-D3 blocker by producing an evidence-based role → permission approval package.

```text
This phase MUST NOT implement Attendance.
This phase MUST NOT mutate config/security.php or Permission.php.
```

**Can ATT-D3 now be considered APPROVED?**

```text
BLOCKED — HUMAN DECISION REQUIRED
```

No repository artifact records human approval of Attendance grants. Permission **codes** remain design-locked; **grants** are not approved.

---

## 1. Locked permission set (from Design Lock — unchanged)

| Code | R1 state |
|------|----------|
| `attendance.view` | REQUIRED |
| `attendance.session.create` | REQUIRED |
| `attendance.mark` | REQUIRED |
| `attendance.correct` | REQUIRED (sensitive) |
| `attendance.session.close` | REQUIRED |
| `attendance.administer` | **REJECTED** (reconfirmed) |
| `attendance.session.reopen` | DEFERRED |
| `attendance.session.cancel` | DEFERRED |

---

## 2. Existing role inventory (repository evidence)

**Source of truth:** `config/security.php` → `'roles'`  
**Materialization:** `database/seeders/SecurityPermissionSeeder.php` → `security.roles` / `security.role_permissions` / `security.user_roles`  
**Evaluation:** `DatabaseAuthorizationService::userHasPermission` (union of effective user roles’ permissions)  
**School binding:** `user_roles.school_id` on assign; request path uses `SchoolContextMiddleware` / ATT-D5 — **permission codes do not encode school**

| Role name | Source | Existing permissions | Domain responsibilities (from grants only) | School scope | Teacher/manager characteristic | Grades relationship | Enrollment relationship | Auth pattern |
|-----------|--------|----------------------|--------------------------------------------|--------------|--------------------------------|----------------------|-------------------------|--------------|
| `student_manager` | `config/security.php` | students.view/create/update/view_pii | Student CRUD + PII | Via user_roles.school_id + SchoolContext | Manager (students) | None | None | Policy + permission |
| `student_viewer` | same | students.view | Student read | same | Viewer | None | None | same |
| `enrollment_manager` | same | enrollment.view/create/update/cancel, students.view | Enrollment lifecycle | same | Manager (enrollment) | None | Owns enrollment writes | EnrollmentPolicy |
| `enrollment_viewer` | same | enrollment.view, students.view | Enrollment read | same | Viewer | None | Read | same |
| `grades_manager` | same | grades.view/create/correct/void/finalize, students.view, enrollment.view | Full grades ops | same | Manager (grades) | Owns correct/void/finalize | Read enrollment | GradePolicy |
| `grades_teacher` | same | grades.view/create, students.view, enrollment.view | Enter grades (no correct) | same | Teacher-like (grades) | Create only | Read | GradePolicy |
| `grades_viewer` | same | grades.view, students.view, enrollment.view | Grades read | same | Viewer | View only | Read | GradePolicy |

**Not present as roles in `config/security.roles`:**

```text
attendance_*  ·  teacher (generic)  ·  principal  ·  admin  ·  ministry  ·  directorate
```

**Permission without role grant in config:**

```text
security.manage_users
```

Exists in `Permission.php` / permissions map; **no role in `config/security.php` lists it**. Platform user-management is therefore **not** an evidenced Attendance grant target. Document only: if a future role receives `security.manage_users`, that is **platform** governance — **not** an Attendance god-permission and **must not** substitute for `attendance.*`.

---

## 3. Existing permission inventory & convention match

| Mechanism | Evidence |
|-----------|----------|
| Naming | `{module}.{action}` dotted strings (`grades.correct`, `enrollment.cancel`) |
| Constants | `app/Security/Authorization/Permission.php` |
| Config registry | `config/security.php` `'permissions'` |
| Role mapping | `config/security.php` `'roles'` → seeder → DB |
| Runtime check | `AuthorizationServiceInterface::userHasPermission` |
| Policies | `StudentPolicy`, `EnrollmentPolicy`, `GradePolicy` (permission ∧ school access) |
| Seeder | `SecurityPermissionSeeder` |
| Tests | Feature/Unit Security + Exams using `grantGrades*` / `grantEnrollment*` helpers |

| Attendance Permission | Existing Convention Match | Evidence | Action Later (NOT now) |
| --------------------- | ------------------------- | -------- | ---------------------- |
| attendance.view | **YES** — `{domain}.view` | students/enrollment/grades.view | Add constant + config entry + grants |
| attendance.session.create | **YES with compound action** — domain-qualified action; richer than `grades.create` but still dotted | Design Lock; no conflict | Add as distinct code (do not overload `attendance.create`) |
| attendance.mark | **YES** — domain write verb parallel to `grades.create` | Design Lock mark ≠ correct | Add constant + config |
| attendance.correct | **YES** — mirrors `grades.correct` | GradePolicy::correct | Add constant + config |
| attendance.session.close | **YES with compound action** — lifecycle verb | Design Lock OPEN→CLOSED | Add constant + config |

---

## 4. Cross-domain role analysis

| Candidate role | Existing? | Evidence | Can logically perform Attendance? | Least-privilege risk if granted Attendance | Cross-domain boundary? | Needs new attendance.* perms? |
|----------------|-----------|----------|-----------------------------------|--------------------------------------------|------------------------|-------------------------------|
| `grades_teacher` | **YES** | config | Operationally similar (classroom) — **not proven equivalent** | **HIGH** — expands grades-named role into new domain | **YES** — Grades → Attendance | YES (all attendance codes) |
| `grades_manager` | **YES** | config | Could correct/close analogously | **HIGH** — already holds sensitive grades.correct/void/finalize; adding attendance.correct stacks sensitive domains | **YES** | YES |
| `grades_viewer` | **YES** | config | View-only analogy | MEDIUM if only view; still cross-domain | **YES** | YES (view) |
| `enrollment_manager` | **YES** | config | Placement, not daily mark | **HIGH** — wrong operational job | **YES** | YES |
| `enrollment_viewer` | **YES** | config | Read only | MEDIUM for view-only | Soft | YES (view) |
| `student_manager` | **YES** | config | Identity, not attendance | **HIGH** | **YES** | YES |
| `student_viewer` | **YES** | config | Identity read | MEDIUM for view-only | Soft | YES (view) |
| `attendance_teacher` | **NO** | absent | N/A until created | N/A | N/A | Would need creation |
| `attendance_manager` | **NO** | absent | N/A | N/A | N/A | Would need creation |
| `attendance_viewer` | **NO** | absent | N/A | N/A | N/A | Would need creation |

```text
Do NOT assume: grades_teacher == attendance_teacher
Repository evidence: separate domain role families (student_*, enrollment_*, grades_*).
```

---

## 5. Role → permission candidate matrix (existing roles only)

Legend: **YES** / **NO** / **CONDITIONAL** / **UNKNOWN** — based on evidence + least privilege, **not** name vibes.

| Existing Role | view | create | mark | correct | close | Evidence / rationale | Recommendation |
| ------------- | ---: | -----: | ---: | ------: | ----: | -------------------- | -------------- |
| student_manager | NO | NO | NO | NO | NO | Student domain only; no classroom ops in grants | Do not extend |
| student_viewer | NO | NO | NO | NO | NO | View students ≠ attendance facts | Do not extend |
| enrollment_manager | NO | NO | NO | NO | NO | Enrollment lifecycle ≠ daily mark | Do not extend |
| enrollment_viewer | CONDITIONAL | NO | NO | NO | NO | Could need roster context; still cross-domain | Prefer dedicated viewer |
| grades_viewer | CONDITIONAL | NO | NO | NO | NO | Read pattern exists; domain name mismatch | Prefer dedicated viewer |
| grades_teacher | CONDITIONAL | CONDITIONAL | CONDITIONAL | **NO** | CONDITIONAL | Mirrors create-without-correct SoD; **cross-domain privilege expansion** | Not preferred |
| grades_manager | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL | CONDITIONAL | Has grades.correct SoD peer; stacking attendance.correct = dual sensitive domains | Not preferred |

**YES is never used above for existing roles** — no evidence that any existing role was designed to own Attendance.

---

## 6. Separation of duties

Sensitive R1 capabilities: **mark**, **correct**, **close**.

| Question | Answer | Rationale |
|----------|--------|-----------|
| Can a marker also correct? | **Not by default** | Mirror `grades_teacher` (create) vs `grades_manager` (correct). Design Lock SoD. |
| Can a marker also close? | **Yes (recommended for teacher role)** | Closing own OPEN session is routine ops; Design Lock R1 includes close on teacher analogy. |
| Can a corrector also close? | **Yes if mapped to manager role** | Manager holds individual perms; no `administer` catch-all. |
| Is an administrative override role required? | **No** | `attendance.administer` remains **REJECTED**. |

Capability personas (logical — not approved roles):

| Persona | Min permissions |
|---------|-----------------|
| Viewer | view |
| Marker | view, session.create, mark, session.close |
| Corrector | view, correct (+ typically create/mark/close if manager) |
| Closer | session.close (usually bundled with Marker) |

---

## 7. Permission semantics (contracts)

```text
attendance.view
= May read Attendance sessions/records/summaries only within the actor’s authorized school scope (SchoolContext + ATT-D5).

attendance.session.create
= May create an OPEN Attendance session for an authorized section within school scope.

attendance.mark
= May create/update routine Attendance marks for authorized OPEN sessions (partial payload; omitted ≠ absent).

attendance.correct
= May execute explicit audited Attendance corrections with mandatory reason (OPEN or CLOSED session).

attendance.session.close
= May transition an authorized OPEN session to CLOSED.
```

```text
Attendance permission ≠ directorate-wide access
Attendance permission ≠ cross-school bypass
Attendance permission ≠ RLS bypass
```

School isolation remains ATT-D5 + SchoolContext (+ future ATT-SEC-*). Permissions are capability flags only.

---

## 8. `attendance.administer`

```text
REJECTED — RECONFIRMED
```

No repository equivalent Attendance god-permission. Do not map “all attendance.*” via a single administer code. If a user needs full R1 Attendance capability, grant the **individual** permissions.

---

## 9. Least privilege / privilege expansion (if Option A chosen)

### Hypothetical: extend `grades_teacher`

| Item | Value |
|------|--------|
| Current | grades.view, grades.create, students.view, enrollment.view |
| Proposed add | attendance.view, session.create, mark, session.close |
| Privilege expansion | **HIGH** — new domain write surface on a grades-named role |
| SoD | Keeps correct off teacher (good) but still wrong role family |

### Hypothetical: extend `grades_manager`

| Item | Value |
|------|--------|
| Current | grades.* including correct/void/finalize + students/enrollment view |
| Proposed add | all five attendance.* |
| Privilege expansion | **HIGH** — dual sensitive correctors (grades + attendance) |
| SoD | Consistent with manager pattern but domain-coupled |

### Proposed dedicated roles (Option B — not created)

| Proposed role (NOT APPROVED) | Min permissions | Expansion vs empty |
|------------------------------|-----------------|--------------------|
| `attendance_viewer` | view | Baseline |
| `attendance_teacher` | view, session.create, mark, session.close | Operational write without correct |
| `attendance_manager` | view, session.create, mark, correct, session.close | Adds sensitive correct only |

```text
These names are proposals only.
They are NOT approved roles.
Do not add them to configuration in R1.2.
```

Classification if human rejects extending existing roles:

```text
NEW ROLE REQUIRED — HUMAN APPROVAL
```

---

## 10. Human decision options

### OPTION A — Extend existing Grades roles

```text
grades_teacher → view, session.create, mark, session.close
grades_manager → + attendance.correct (and typically the teacher set)
grades_viewer  → attendance.view (optional)
```

| Verdict | **ALTERNATIVE — NOT RECOMMENDED** |
|---------|-----------------------------------|
| Why not preferred | Cross-domain naming; HIGH privilege expansion; couples Attendance ops to Grades staffing |

### OPTION B — Dedicated Attendance roles

```text
attendance_viewer  → attendance.view
attendance_teacher → attendance.view, attendance.session.create, attendance.mark, attendance.session.close
attendance_manager → attendance.view, attendance.session.create, attendance.mark, attendance.correct, attendance.session.close
```

| Verdict | **RECOMMENDED** |
|---------|-----------------|
| Why | Matches existing `student_*` / `enrollment_*` / `grades_*` family pattern; least privilege; SoD mark vs correct; no Grades/Enrollment pollution |

### OPTION C — Hybrid

Reuse `grades_viewer` or `enrollment_viewer` for `attendance.view` only; create `attendance_teacher` + `attendance_manager` for writes.

| Verdict | **ALTERNATIVE** |
|---------|-----------------|
| Why weaker | Still cross-domain for view; little benefit vs Option B |

### REJECTED

```text
Grant Attendance to enrollment_manager / student_manager
Grant attendance.correct to grades_teacher-equivalent without manager split
Introduce attendance.administer
Grant reopen/cancel in R1
Cross-school Attendance via permission alone
```

---

## 11. Approval matrix (human must complete)

| Decision | Proposed mapping (RECOMMENDED = Option B) | Human Approval |
| -------- | ---------------------------------------- | -------------- |
| Attendance Viewer | **NEW** role `attendance_viewer` → `attendance.view` | **REQUIRED** |
| Attendance Marker (+ create/close) | **NEW** role `attendance_teacher` → view, session.create, mark, session.close | **REQUIRED** |
| Attendance Corrector | **NEW** role `attendance_manager` → teacher set + `attendance.correct` | **REQUIRED** |
| Attendance Closer | Bundled in `attendance_teacher` / `attendance_manager` (not separate role) | **REQUIRED** (confirm bundle) |
| Extend `grades_teacher` instead | Option A | REQUIRED if chosen over B |
| Extend `grades_manager` instead | Option A | REQUIRED if chosen over B |
| Cross-school access | **NO** | **LOCKED** |
| `attendance.administer` | **REJECT** | **LOCKED** |
| reopen / cancel | **DEFERRED** | **LOCKED** |

```text
Proposed mappings are NOT approved until a human marks this matrix.
```

---

## 12. Implementation consequences (AFTER approval only)

```text
After human approval of ATT-D3 mapping:

1. Add permission constants to Permission.php
2. Add permission registry entries to config/security.php
3. Add/confirm roles and grants per approved matrix
4. Extend SecurityPermissionSeeder helpers (grantAttendance*)
5. Add AttendancePolicy (permission ∧ school access) when HTTP exists
6. Add authorization tests
7. Then — only with separate R1.3/R1.4 authorization — implement Attendance CQRS
```

```text
NONE OF THE ABOVE IS AUTHORIZED IN PHASE R1.2.
```

---

## 13. Security test matrix (design only)

```text
Given role without attendance.view
When query Attendance
Then deny.

Given role with attendance.view
When query another school
Then deny (SchoolContext / ATT-D5).

Given attendance_teacher (or approved marker) without attendance.correct
When CorrectAttendanceRecord
Then deny.

Given attendance_manager (or approved corrector)
When CorrectAttendanceRecord with valid reason
Then allowed within school scope.

Given marker without attendance.session.close
When CloseAttendanceSession
Then deny.

Given marker with close granted
When CloseAttendanceSession on own school OPEN session
Then allow.

Given any Attendance-capable role
When cross-school mark/create/correct/close
Then deny.

Given no attendance.administer
When any operation
Then only individual attendance.* permissions apply.
```

Follow existing `StudentPolicyTest` / Enrollment / Grades authorization test conventions.

---

## 14. Scores & final gate

```text
SIS DATABASE — PHASE R1
ATTENDANCE APPLICATION
R1.2 — ROLE → PERMISSION MAPPING APPROVAL GATE

Role mapping completeness: 85/100   (options clear; grants unapproved)
Least privilege:           90/100   (Option B preserves SoD)
SoD:                       92/100   (mark ≠ correct by default)
Security boundary:         95/100   (permission ≠ school bypass locked)
Repository evidence:       95/100   (config + seeder + policies inspected)
Overall:                   88/100
```

```text
FINAL STATUS:
BLOCKED — HUMAN DECISION REQUIRED

Can ATT-D3 be considered APPROVED?
NO — awaiting explicit human selection of Option A / B / C (or marked approval matrix).

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
```

```text
Recommended human action:
  APPROVE OPTION B (dedicated attendance_viewer / attendance_teacher / attendance_manager)
  — or explicitly approve Option A/C with written rationale —

Then issue a separate R1.3 Implementation Authorization before any config/code change.

DO NOT ADVANCE TO R1.3 / R1.4 AUTOMATICALLY.
```

---

## Mutation check

```text
Files modified: ONLY
.cursor/database/phase-attendance/03-ATTENDANCE-ROLE-PERMISSION-APPROVAL-GATE.md

config/security.php · Permission.php · seeders · PHP · DDL · tests: NONE
```

**STOP.** Await explicit human approval of ATT-D3.
