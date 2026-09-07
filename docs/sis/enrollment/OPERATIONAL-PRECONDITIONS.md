# Enrollment Module — Operational Preconditions

**Date:** 2026-09-07  
**Purpose:** Items that must **not block module development** but must be tracked for **Production Cutover**

Distinction:

| Category | Blocks module gate? | Blocks production? |
|----------|---------------------|--------------------|
| Module implementation gaps | **Yes** | — |
| Operational preconditions | **No** (if documented) | **Yes** |

---

## Preconditions Register

### OP-001 — `students.students.school_id` NOT NULL

| Field | Value |
|-------|-------|
| **Status** | PENDING |
| **Current** | Column nullable (Phase 3.10.1 migration) |
| **Impact** | Student tenant anchor incomplete; enrollment should use enrollment.school_id as source of truth per lifecycle docs |
| **Blocking** | Production cutover hardening |
| **Mitigation** | Backfill from active enrollment; enforce NOT NULL in future migration |
| **Owner** | TBD |
| **Due** | Before production cutover |

---

### OP-002 — PostgreSQL RLS on all enrollment tables

| Field | Value |
|-------|-------|
| **Status** | PARTIAL |
| **Current** | RLS fail-closed on `enrollment.enrollments` only |
| **Missing** | `enrollment.classes`, `enrollment.sections`, `enrollment.enrollment_subjects` |
| **Impact** | Direct DB access to class/section rows without school context |
| **Blocking** | Production cutover ONLY |
| **Mitigation** | Extend RLS policies when read/write paths exist for those tables |
| **Evidence** | `2026_09_07_120100_fix_rls_fail_closed.php` (enrollments only) |

---

### OP-003 — Partial unique index (one active enrollment per year)

| Field | Value |
|-------|-------|
| **Status** | PENDING |
| **Current** | App check in `hasActiveEnrollment()` only |
| **Blueprint** | `UNIQUE(student_id, academic_year_id) WHERE status = 1` |
| **Impact** | Race condition could create duplicate active enrollments |
| **Blocking** | Production cutover |
| **Mitigation** | Add partial unique migration after measuring workload |

---

### OP-004 — PostgreSQL RLS tests in CI

| Field | Value |
|-------|-------|
| **Status** | PENDING |
| **Current** | `PostgreSqlRlsFailClosedTest` skipped on SQLite (phpunit default) |
| **Impact** | RLS regressions not caught in default CI |
| **Blocking** | Production confidence |
| **Mitigation** | Add PG service job in CI or run locally before release |

---

### OP-005 — Enrollment security audit to PostgreSQL

| Field | Value |
|-------|-------|
| **Status** | **CLOSED** (Phase A) |
| **Current** | `RecordStudentEnrolledAudit` → `SecurityAuditLoggerInterface` → `security.security_audit_logs` |
| **Evidence** | `EnrollmentAuditPersistenceTest`, `CrossSchoolEnrollmentTest` (denial audit) |
| **Closed** | 2026-09-07 |

---

## Accepted from Phase 3.10.1 (Do Not Reopen)

| Item | Status |
|------|--------|
| Phase 3.10.1 security baseline | APPROVED |
| Production autonomous execution | BLOCKED |
| Composer audit CI gate | PASS |
| Student API school isolation | PASS |

Regression in these areas during Enrollment work → record as **Regression** finding, not full phase redo.

---

## Must Fix Before Module Approval

| ID | Item | Severity | Status |
|----|------|----------|--------|
| M-001 | HTTP API with auth + policy + school context | High | **CLOSED** |
| M-002 | Cross-school validation in EnrollStudentHandler | High | **CLOSED** |
| M-003 | Enrollment permissions in security config | High | **CLOSED** |
| M-004 | Security audit persistence for enroll events | High | **CLOSED** |
| M-005 | Mass assignment protection on API | High | **CLOSED** |
| M-006 | Executable SEC-* matrix (except N/A rows) | High | **CLOSED** |
| M-007 | Read queries (list/show) with school scope | Medium | **CLOSED** |

See `ENROLLMENT-GATE-REPORT.md` for gate decision.

---

## Must Fix Before Production Cutover Only

| ID | Item |
|----|------|
| OP-001 | students.school_id NOT NULL + backfill |
| OP-002 | RLS on classes/sections/subjects |
| OP-003 | Partial unique DB constraint |
| OP-004 | PG RLS in CI pipeline |
