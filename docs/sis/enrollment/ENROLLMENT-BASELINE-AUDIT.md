# Enrollment Module — Baseline Audit Report

**Date:** 2026-09-07  
**Phase:** STEP 1 — Discovery & Baseline Audit  
**Predecessor:** Phase 3.10.1 — APPROVED  
**Code modified:** **NONE** (read-only audit)

---

## Executive Summary

Enrollment exists as a **reference Clean Architecture write slice** (`EnrollStudent`) with solid patterns (UoW, outbox, idempotency, specification) but is **not a deliverable module**:

- No HTTP API, permissions, policy, or frontend
- Handler not wired to any entry point
- No read side (queries)
- Application-layer security parity with Students **not achieved**
- PostgreSQL RLS on `enrollment.enrollments` **passes** fail-closed tests

**Recommendation:** Proceed to **STEP 7–8 (Implementation Plan + Minimal Changes)** — do not block on RLS/`school_id NOT NULL` for development; track as operational preconditions.

---

## 1. Files Inspected

### Application / Domain / Infrastructure (18 files)
- `app/Application/Enrollment/**`
- `app/Domain/Enrollment/**`
- `app/Infrastructure/Persistence/Enrollment/EloquentEnrollmentRepository.php`
- `app/Infrastructure/Persistence/Eloquent/EnrollmentRecord.php`
- `app/Listeners/Enrollment/RecordStudentEnrolledAudit.php`
- `app/Providers/ArchitectureServiceProvider.php`

### Security / Architecture context
- `config/security.php`, `routes/api.php`, `bootstrap/app.php`
- `app/Security/**` (Students patterns — reference only)
- `.cursor/architecture/database-blueprint.md`
- `database/migrations/2026_09_05_100500_create_enrollment_tables.php`
- `database/migrations/2026_09_07_120100_fix_rls_fail_closed.php`

### Tests (4 files)
- `tests/Unit/Enrollment/*`
- `tests/Architecture/ArchitectureHardeningTest.php`
- `tests/Feature/Security/PostgreSqlRlsFailClosedTest.php`

### Documentation
- `.cursor/brain/student-lifecycle.md`
- `docs/security/PHASE-3.10.1-GATE-REPORT.md`

---

## 2. Files Changed

**None** — audit phase only.

---

## 3. Files Created (Documentation)

```text
docs/sis/enrollment/
├── ENROLLMENT-INVENTORY.md
├── ENROLLMENT-LIFECYCLE.md
├── SECURITY-CONTRACT.md
├── SECURITY-TEST-MATRIX.md
├── OPERATIONAL-PRECONDITIONS.md
├── ARCHITECTURE-COMPLIANCE.md
└── ENROLLMENT-BASELINE-AUDIT.md (this file)
```

---

## 4. Database Objects Inspected

| Object | Notes |
|--------|-------|
| `enrollment.classes` | school_id, academic_year_id, grade_level_id |
| `enrollment.sections` | class_id, homeroom_teacher_id |
| `enrollment.enrollments` | Full tenant + lifecycle columns; RLS fail-closed |
| `enrollment.enrollment_subjects` | enrollment_id, subject_id |
| RLS policy `enrollment_school_isolation` | Requires non-empty `app.current_school_id` |

---

## 5. Security Contract — Baseline Status

| Contract | Baseline Status |
|----------|-----------------|
| SC-01 Authentication | NOT APPLICABLE (no API) |
| SC-02 Authorization | **FAIL** |
| SC-03 Tenant Isolation | **PARTIAL** (RLS only) |
| SC-04 IDOR | **NOT TESTED** |
| SC-05 Mass Assignment | **RISK** (fillable + command fields) |
| SC-06 State Transition | N/A (single create path) |
| SC-07 Input Validation | **PARTIAL** |
| SC-08 SQL Safety | **PASS** |
| SC-09 Auditability | **FAIL** |
| SC-10 Bulk Security | N/A |

---

## 6. Security Tests Executed (Existing)

| Test | Result |
|------|--------|
| `EnrollStudentHandlerTest` (5) | PASS (unit, mocked) |
| `EligibleForEnrollmentSpecificationTest` (2) | PASS |
| `PostgreSqlRlsFailClosedTest` | PASS on PG / SKIP on SQLite |
| SEC-001–SEC-020 matrix | Mostly **NOT RUN** |

---

## 7. Findings

| ID | Severity | Finding | Classification | Action |
|----|----------|---------|----------------|--------|
| F-001 | **High** | No HTTP API for enrollment | Gap | Add secured routes + controller |
| F-002 | **High** | No EnrollmentPolicy / permissions | Gap | Add `enrollment.*` + policy |
| F-003 | **High** | Handler trusts schoolId/classId/sectionId without cross-validation | Gap | Add domain validation service |
| F-004 | **High** | Audit uses Log::info not SecurityAuditLogger | Gap | Wire structured audit |
| F-005 | **Medium** | No read queries despite DTOs | Gap | Implement CQRS read side |
| F-006 | **Medium** | Handler orphaned — no caller | Gap | Wire controller/command |
| F-007 | **Medium** | EnrollmentRecord mass-assignable school_id | Risk | Match Student forceFill pattern |
| F-008 | **Medium** | enrolled_by client-trusted in command | Risk | Server-side actor only |
| F-009 | **Low** | No EnrollmentStatus enum / workflow | Gap | Defer until cancel/transfer |
| F-010 | **Low** | Partial unique index not migrated | Operational | OP-003 |
| F-011 | **Info** | RLS only on enrollments table | Operational | OP-002 |
| F-012 | **Info** | Admission/Transfer blueprint only | Future module | Out of scope |

**Critical:** 0  
**High:** 4 (module blockers)  
**Medium:** 4  
**Low/Info:** 4

---

## 8. Architecture Gaps

| Gap | Priority |
|-----|----------|
| Missing Presentation layer | P0 |
| Missing Query handlers | P1 |
| No domain Enrollment entity | P2 |
| Audit listener not production-grade | P1 |

Architecture fitness for **existing code** passes; feature is **incomplete** per FEATURE-DONE.

---

## 9. Test Gaps

| Gap | Priority |
|-----|----------|
| No feature/integration tests with real DB | P0 |
| No enrollment security feature tests | P0 |
| No cross-school enrollment tests | P0 |
| No API authorization tests | P0 |

---

## 10. Operational Preconditions

See `OPERATIONAL-PRECONDITIONS.md`:

- OP-001: `students.school_id NOT NULL` — production cutover
- OP-002: RLS on all enrollment tables — production cutover
- OP-003: Partial unique index — production cutover
- OP-004: PG RLS in CI — production confidence

**These do NOT block starting implementation.**

---

## 11. Proposed Implementation Plan (STEP 7)

### Phase A — Minimum viable secured enroll API
1. Add `enrollment.view`, `enrollment.create` permissions + roles
2. Add `EnrollmentPolicy` + `EnrollmentSchoolAccessService` (mirror Students)
3. Add `EnrollStudentRequest` with `SecuritySensitiveFieldGuard`
4. Add `EnrollmentController@store` → `EnrollStudentHandler`
5. Validate student/class/section belong to school context
6. Set `enrolledBy` from authenticated user (never request)
7. Wire routes: `auth:sanctum`, school context middleware
8. Wire `SecurityAuditLogger` on enroll + denials

### Phase B — Read + tests
1. `GetEnrollmentQuery` / `ListEnrollmentsQuery` with school scope
2. Feature tests: happy path + SEC matrix
3. Cross-school tests
4. Integration test with RefreshDatabase

### Phase C — Gate
1. Run full regression + security suite
2. Generate `ENROLLMENT-GATE-REPORT.md`
3. STOP for human approval

**Out of scope this module:** Admission, Transfer, bulk export, frontend UI.

---

## 12. Baseline Scores (Pre-Implementation)

| Dimension | Score | Notes |
|-----------|-------|-------|
| Functional | 35/100 | Write handler only, not wired |
| Architecture | 70/100 | Good write slice; missing read + HTTP |
| Security | 25/100 | RLS pass; no app-layer parity |
| Testing | 30/100 | 7 unit + 2 RLS |
| Documentation | 85/100 | After this audit |

**Overall (weighted):** ~**42/100** — **NOT GATED** — audit baseline only.

---

## 13. Gate Decision (This Phase)

```text
==================================================
ENROLLMENT MODULE — BASELINE AUDIT
==================================================

Status:
AUDIT COMPLETE — IMPLEMENTATION NOT STARTED

Gate Decision:
NOT READY (expected at this step)

Critical: 0
High: 4 (documented module blockers)
Medium: 4

Next Step:
Human approval to proceed with Implementation Phase A

==================================================
HUMAN APPROVAL REQUIRED BEFORE IMPLEMENTATION
==================================================
```

---

## 14. Recommended Next Step

1. **Human review** of this baseline audit + security contract  
2. On approval → begin **Phase A** (minimal secured enroll API)  
3. Do **not** start Attendance, Grades, or other modules  
4. Do **not** reopen Phase 3.10.1 unless regression proven

---

**STOP — WAIT FOR HUMAN APPROVAL TO BEGIN IMPLEMENTATION**
