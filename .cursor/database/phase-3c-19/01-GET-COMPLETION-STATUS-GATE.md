# PHASE 3C.19.1
# GET COMPLETION STATUS QUERY GATE

**Date:** 2026-09-11  
**Unit:** UNIT-3C19-01 `GetCompletionStatusQuery`  
**Mode:** IMPLEMENTATION — ONE UNIT ONLY  

---

## 1. Scope

Implemented **only** the pure read path:

```text
GetCompletionStatusQuery
+ GetCompletionStatusHandler
+ GraduationReadRepositoryInterface
+ EloquentGraduationReadRepository
+ CompletionStatusDTO
+ CompletionOutcomeNotFoundException
+ Unit + PostgreSQL school/RLS tests
```

No HTTP, permissions, migrations, write-handler edits, StudentStatus, PublishAward, EvidenceSet writes, or other queries.

---

## 2. Contract

### Input

```text
schoolId: int
enrollmentId: int
```

Identity = `school_id + enrollment_id` (HD-39).

### Output

`CompletionStatusDTO` — factual SSOT columns only (see §7). No invented institutional status labels.

### Not-found behavior

`CompletionOutcomeNotFoundException` — matches Enrollment/Exams NotFound convention.

### School isolation

1. `GraduationAuthorityPort::assertSchoolMatches(schoolId)` (SchoolContext fail-closed)  
2. Repository `WHERE school_id = ? AND enrollment_id = ?`  
3. PostgreSQL RLS + FORCE RLS (verified via `sis_rls_tester` NOBYPASSRLS role)

### Nullability

Outcome always present when found. Version snapshot fields nullable when no `completion_outcome_versions` row exists.

### Status semantics

Expose persisted SMALLINT columns (`lifecycle_status`, `evaluation_status`, `eligibility_status`, `is_current_official`) **as stored**. No mapping to words like graduated/eligible/pending.

### Version selection (architectural, not institutional policy)

Prefer `is_current_official DESC`, then `version_no DESC` (LATERAL LIMIT 1). Documented; does not invent eligibility rules.

### Source tables

`graduation.completion_outcomes` LEFT JOIN LATERAL `graduation.completion_outcome_versions`.

Does **not** join approvals, awards, evidence, or `students.status`.

---

## 3. Authoritative Sources

| Source | Use |
|--------|-----|
| Phase 3C.18 Scope Lock | First unit authorization target |
| Phase 3C.9 query list | Name `GetCompletionStatus` |
| HD-39 / UNIQUE | Identity |
| 3C.12 schema | Columns + RLS |
| Enrollment/Exams query patterns | NotFound + DTO + read port |
| DL-022 | Exclude StudentStatus |

---

## 4. Files Changed

| Path | Action |
|------|--------|
| `app/Application/Graduation/Queries/GetCompletionStatusQuery.php` | Added/filled |
| `app/Application/Graduation/Queries/GetCompletionStatusHandler.php` | Added/filled |
| `app/Application/Graduation/DTOs/CompletionStatusDTO.php` | Added |
| `app/Application/Graduation/Contracts/GraduationReadRepositoryInterface.php` | Added |
| `app/Infrastructure/Persistence/Graduation/EloquentGraduationReadRepository.php` | Added |
| `app/Domain/Graduation/Exceptions/CompletionOutcomeNotFoundException.php` | Added |
| `app/Providers/ArchitectureServiceProvider.php` | Bind read repo |
| `tests/Unit/Graduation/GetCompletionStatusHandlerTest.php` | Added |
| `tests/Feature/Database/PostgreSql/GetCompletionStatusPostgreSqlTest.php` | Added |
| `tests/Support/Database/PostgreSqlRlsActor.php` | Grant `graduation` schema to RLS tester |
| `.cursor/architecture/features/Graduation.md` | Mark query ✅ |
| `.cursor/database/phase-3c-19/01-GET-COMPLETION-STATUS-GATE.md` | This report |

---

## 5. Files Not Changed

Write handlers (Create/Evaluate/Approve/Issue/Revoke/Publish) · Permission.php · routes · controllers · migrations · RLS policies · StudentStatus · EvidenceSet write path · other Graduation queries.

---

## 6. Query Flow

```text
GetCompletionStatusQuery
  → GetCompletionStatusHandler
      → authority.assertSchoolMatches(schoolId)
      → GraduationReadRepositoryInterface.findCompletionStatus(schoolId, enrollmentId)
          → EloquentGraduationReadRepository (single SQL, explicit columns)
      → null → CompletionOutcomeNotFoundException
      → CompletionStatusDTO
```

Read-only. No UnitOfWork write, outbox, or idempotency.

---

## 7. DTO Fields and Sources

| Field | Source | Classification |
|-------|--------|----------------|
| schoolId | `completion_outcomes.school_id` | AUTHORITATIVE |
| enrollmentId | `completion_outcomes.enrollment_id` | AUTHORITATIVE |
| completionOutcomeId | `completion_outcomes.id` | AUTHORITATIVE |
| studentId | `completion_outcomes.student_id` | AUTHORITATIVE (denormalized; not identity) |
| academicYearId | `completion_outcomes.academic_year_id` | AUTHORITATIVE |
| currentOfficialVersionId | `completion_outcomes.current_official_version_id` | AUTHORITATIVE |
| versionId | `completion_outcome_versions.id` | AUTHORITATIVE |
| versionNo | `completion_outcome_versions.version_no` | AUTHORITATIVE |
| lifecycleStatus | `completion_outcome_versions.lifecycle_status` | AUTHORITATIVE |
| evaluationStatus | `completion_outcome_versions.evaluation_status` | AUTHORITATIVE |
| eligibilityStatus | `completion_outcome_versions.eligibility_status` | AUTHORITATIVE |
| isCurrentOfficial | `completion_outcome_versions.is_current_official` | AUTHORITATIVE |
| eligibilityPolicyVersionId | `completion_outcome_versions.eligibility_policy_version_id` | AUTHORITATIVE |
| calculationVersion | `completion_outcome_versions.calculation_version` | AUTHORITATIVE |
| evaluatedAt | `completion_outcome_versions.evaluated_at` | AUTHORITATIVE |

**Not included (deferred / other queries / invent risk):** approval, award, publication, evidence, StudentStatus, string status labels.

---

## 8. School Isolation

| Layer | Mechanism |
|-------|-----------|
| Application | `assertSchoolMatches` |
| SQL | `WHERE school_id = ? AND enrollment_id = ?` |
| PostgreSQL | ENABLE + FORCE RLS school_isolation policy |

---

## 9. RLS Verification

`GetCompletionStatusPostgreSqlTest::rls_actor_under_school_b_cannot_see_school_a_completion_outcomes`:

- Seeds School A + School B outcomes as owner  
- `PostgreSqlRlsActor::become()` (`sis_rls_tester` NOSUPERUSER NOBYPASSRLS)  
- GUC = School B → SELECT sees only School B id  
- Repository find for School A identity returns null  

`PostgreSqlRlsActor` updated to GRANT `graduation` schema (required; was missing).

---

## 10. Tests

| Suite | Result |
|-------|--------|
| `GetCompletionStatusHandlerTest` | 4/4 PASS |
| `GetCompletionStatusPostgreSqlTest` | 4/4 PASS |

---

## 11. Architecture Validation

```text
php artisan architecture:validate --fitness → PASS
php artisan architecture:feature-check Graduation → PASS
```

---

## 12. Security Validation

- No Permission.php changes  
- No HTTP surface  
- Cross-school denied at authority + RLS  
- StudentStatus not read  

`security:validate` not required for this narrow unit; no security surface changed beyond read isolation tests.

---

## 13. Performance Observations

| Item | Note |
|------|------|
| Logical queries | 1 SQL per call |
| N+1 | None |
| SELECT * | Avoided — explicit columns |
| Index | Uses UNIQUE `(school_id, enrollment_id)` on outcomes |
| New index | **Not required** — no INDEX GAP |
| Claims | No production performance claims |

---

## 14. Findings

| ID | Finding | Action |
|----|---------|--------|
| F-01 | Test DB owner/superuser bypasses RLS; must use `PostgreSqlRlsActor` | Documented; test fixed |
| F-02 | `PostgreSqlRlsActor` lacked `graduation` grants | Fixed in test support only |
| F-03 | Approval/Award not in this DTO | Intentional — separate 3C.19 units |

No write-path defects fixed (none in scope).

---

## 15. Deferred Items

- GetRequirementEvaluations / Approval / Award / History / Provenance queries  
- HTTP + HD-31-G  
- EvidenceSet on read  
- Institutional status labels  

---

## 16. Mutation Check

```text
AUTHORIZED CODE CHANGES: GetCompletionStatus query stack ONLY (+ RLS tester graduation grants)

HTTP CHANGES: NONE
ROUTE CHANGES: NONE
CONTROLLER CHANGES: NONE
PERMISSION CHANGES: NONE

MIGRATIONS: NONE
SCHEMA CHANGES: NONE
INDEX CHANGES: NONE
RLS CHANGES: NONE (policies untouched; test role grants only)

DATA MUTATION: NONE (tests on sis_test only)

WRITE HANDLER CHANGES: NONE
StudentStatus CHANGES: NONE
PublishAward CHANGES: NONE
EvidenceSet WRITE CHANGES: NONE
```

---

## 17. Gate Decision

```text
PHASE 3C.19.1 FINAL GATE: PASS
```

---

```text
PHASE 3C.19.1

UNIT:
GetCompletionStatusQuery

AUTHORIZATION:
ONE UNIT ONLY

HTTP:
NOT AUTHORIZED

PERMISSIONS:
NOT AUTHORIZED

DATABASE MUTATION:
NOT AUTHORIZED

WRITE PATH:
NOT AUTHORIZED TO MODIFY

NEXT QUERY:
NOT AUTHORIZED

FINAL STATUS:
PASS
```

**STOP.** Do not proceed to the next Graduation query without a new human authorization.
