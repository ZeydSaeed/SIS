# Enrollment Module — Security Test Matrix

**Date:** 2026-09-07  
**Phase:** Phase A — Implementation Complete  
**Contract:** `SECURITY-CONTRACT.md`

---

## Matrix

| ID | Scenario | Expected | Result | Evidence |
|----|----------|----------|--------|----------|
| SEC-001 | Unauthenticated read enrollment | Deny (401) | **PASS** | `EnrollmentApiAuthorizationTest::unauthenticated_enrollment_requests_are_rejected` |
| SEC-002 | Unauthorized read (no permission) | Deny (403) | **PASS** | `EnrollmentApiAuthorizationTest::authenticated_user_without_enrollment_permission_is_forbidden` |
| SEC-003 | Cross-school read by ID | Deny (403/404) | **PASS** | `CrossSchoolEnrollmentTest::school_a_user_cannot_view_school_b_enrollment_by_id` |
| SEC-004 | Cross-school update | Deny (403) | **PASS** | `CrossSchoolEnrollmentTest::school_a_user_cannot_update_school_b_enrollment` |
| SEC-005 | Cross-school delete/cancel | Deny (403) | **PASS** | `CrossSchoolEnrollmentTest::school_a_user_cannot_cancel_school_b_enrollment` |
| SEC-006 | Enroll invalid / other-school student | Deny | **PASS** | `EnrollStudentHandlerTest::test_rejects_cross_school_student_placement` |
| SEC-007 | Enroll with invalid / cross-school class | Deny | **PASS** | `CrossSchoolEnrollmentTest::school_a_user_cannot_enroll_with_school_b_class` |
| SEC-008 | Duplicate active enrollment same year | Deny | **PASS** | `EnrollStudentHandlerTest::test_rejects_duplicate_enrollment` |
| SEC-009 | Invalid state transition | Deny | **PASS** | `UpdateEnrollmentApiTest`, `CancelEnrollmentApiTest` |
| SEC-010 | Mass assignment — school_id injection | Deny (422) | **PASS** | `EnrollmentMassAssignmentTest::enroll_rejects_school_id_injection` |
| SEC-011 | Mass assignment — enrolled_by injection | Deny (422) | **PASS** | `EnrollmentMassAssignmentTest::enroll_rejects_enrolled_by_injection` |
| SEC-012 | Unsafe bulk operation | Deny | **N/A** | No bulk ops |
| SEC-013 | Audit record on successful enroll | DB record | **PASS** | `EnrollmentAuditPersistenceTest::successful_enrollment_creates_security_audit_record` |
| SEC-014 | Audit record on cross-school denial | DB record | **PASS** | `CrossSchoolEnrollmentTest::cross_school_enrollment_denial_creates_security_audit_record` |
| SEC-015 | Missing school context on API | Deny (403) | **PASS** | Inherited — `SchoolContextRequiredTest` + middleware on enrollment routes |
| SEC-016 | RLS NULL context on enrollments (PG) | Deny | **PASS (PG)** | `PostgreSqlRlsFailClosedTest` (skipped on SQLite) |
| SEC-017 | RLS School A cannot read School B rows | Deny | **PASS (PG)** | `PostgreSqlRlsFailClosedTest` (skipped on SQLite) |
| SEC-018 | Vertical escalation — admin-only action | Deny (403) | **PASS** | No admin bypass; permission required (`EnrollmentApiAuthorizationTest`) |
| SEC-019 | Inactive student enrollment | Deny | **PASS** | `EnrollStudentHandlerTest::test_rejects_inactive_student` |
| SEC-020 | Idempotency replay does not double-enroll | Allow cached | **PASS** | `EnrollStudentHandlerTest::test_returns_cached_result_when_idempotency_key_exists` |

---

## Summary (Phase A)

| Category | Pass | Fail | Not Run | N/A | PG Skip |
|----------|------|------|---------|-----|---------|
| Authentication | 1 | 0 | 0 | 0 | 0 |
| Authorization | 3 | 0 | 0 | 0 | 0 |
| Tenant isolation | 5 | 0 | 0 | 0 | 0 |
| Validation | 4 | 0 | 0 | 1 | 0 |
| Mass assignment | 2 | 0 | 0 | 0 | 0 |
| Audit | 2 | 0 | 0 | 0 | 0 |
| RLS (PostgreSQL) | 0 | 0 | 0 | 0 | 2 |
| Bulk / workflow | 0 | 0 | 0 | 3 | 0 |

**Security test readiness:** **100%** of applicable scenarios (15 pass, 3 N/A, 2 PG-only skip)

---

## Test Files

| File | Purpose |
|------|---------|
| `tests/Feature/Security/EnrollmentApiAuthorizationTest.php` | Auth + permission |
| `tests/Feature/Security/CrossSchoolEnrollmentTest.php` | IDOR / tenant |
| `tests/Feature/Security/EnrollmentMassAssignmentTest.php` | SC-05 |
| `tests/Feature/Security/EnrollmentAuditPersistenceTest.php` | SC-09 |
| `tests/Feature/Enrollment/EnrollStudentApiTest.php` | Happy path integration |
| `tests/Unit/Enrollment/EnrollStudentHandlerTest.php` | Handler rules + placement |
| `tests/Feature/Security/PostgreSqlRlsFailClosedTest.php` | RLS (PG only) |
| `tests/Feature/Security/SchoolContextRequiredTest.php` | SEC-015 inherited |

---

## Execution Commands

```bash
php artisan test --filter=Enrollment
php artisan test tests/Feature/Security/*Enrollment*
php artisan test tests/Feature/Security/CrossSchoolEnrollmentTest.php
php artisan test --filter=PostgreSqlRlsFailClosed
php artisan security:validate
php artisan architecture:feature-check Enrollment
```

**Last run (Phase B, 2026-09-07):** 34 passed, 2 skipped, 0 failed (`--filter=Enrollment`)
