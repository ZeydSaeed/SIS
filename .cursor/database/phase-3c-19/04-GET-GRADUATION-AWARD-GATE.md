# PHASE 3C.19.4
# GET GRADUATION AWARD QUERY GATE

**Date:** 2026-09-11  
**Unit:** UNIT-3C19-04 `GetGraduationAwardQuery`  
**Mode:** IMPLEMENTATION — ONE UNIT ONLY  
**Predecessors:** 3C.19.1–3 PASS · `04-GET-GRADUATION-AWARD-AUDIT.md` · `04A-GET-GRADUATION-AWARD-CONDITION-CLOSURE.md`

```text
The fallback proposed in the parent audit was withdrawn by 04A.

No is_current_issued/version_no fallback was implemented.

Pointer-only semantics were implemented.
```

---

## 1. Scope

```text
GetGraduationAwardQuery
+ GetGraduationAwardHandler
+ GraduationAwardDTO / GraduationAwardVersionDTO
+ GraduationReadRepositoryInterface::findGraduationAward
+ EloquentGraduationReadRepository (pointer-only LEFT JOIN)
+ Unit + PostgreSQL school/RLS + mandatory negative tests
+ Feature contract update + this gate report
```

**Out of scope (not touched):** HTTP · routes · controllers · Permission.php · migrations · indexes · RLS policies · IssueAward / RevokeAward / PublishAward · StudentStatus · SoD · GetOutcomeHistory · GetProvenance · revocation_records join.

---

## 2. Contract

### Input

```text
schoolId + enrollmentId
```

### Output

`?GraduationAwardDTO`:

- Award identity from `graduation.graduation_awards`
- Optional `version` = pointed `graduation_award_versions` row via `current_issued_version_id` only

### No award

Missing `graduation_awards` row → `null` (handler does **not** throw `CompletionOutcomeNotFoundException`).

### Null pointer

Award exists + `current_issued_version_id IS NULL` → award DTO with `version = null`.

---

## 3. Award identity / cardinality

| Layer | Cardinality | Evidence |
|-------|-------------|----------|
| Award identity | **0..1** per `(school_id, enrollment_id)` | `graduation_awards_school_enrollment_uq` |
| Award versions | **0..N** per award | `UNIQUE (graduation_award_id, version_no)` |
| Query return | Single DTO or null — **not** a collection of awards | Implementation |

Lookup is **not** keyed by `completion_outcome_version_id`.

---

## 4. Pointer-only version semantics

```text
graduation_awards.current_issued_version_id
        ↓
LEFT JOIN graduation_award_versions v
  ON v.id = a.current_issued_version_id
 AND v.school_id = a.school_id
```

| Condition | Behavior |
|-----------|----------|
| Pointer non-null | Return that version’s raw facts |
| Pointer NULL | `version = null` — **no** alternate selection |
| Pointed version revoked (`lifecycle_status=3`, `is_current_issued=false`) | Still return that pointed row |
| Another version has `is_current_issued=true` | **Ignored** unless it is the pointer target |

**Forbidden (not present in SQL):**

```text
ORDER BY is_current_issued DESC
ORDER BY version_no DESC
ORDER BY awarded_at DESC
MAX(id) / MAX(created_at)
LATERAL fallback
```

---

## 5. No-award semantics

```text
No graduation_awards row → null
```

CompletionOutcome existence is **not** a prerequisite.

---

## 6. Revocation semantics

Revocation remains a persisted fact on the pointed version:

- `lifecycle_status = 3`
- `is_current_issued = false`

Returned as raw fields. No hide/replace/StudentStatus/write-path call.

`revocation_records` **not** joined (outside this unit).

---

## 7. DTO contract

### `GraduationAwardDTO`

`schoolId`, `enrollmentId`, `awardId`, `studentId`, `academicYearId`, `specializationId`, `currentIssuedVersionId`, `createdBy`, `createdAt`, `?version`

### `GraduationAwardVersionDTO`

`awardVersionId`, `versionNo`, `graduationApprovalId`, `completionOutcomeVersionId`, `lifecycleStatus`, `isCurrentIssued`, `awardedAt`, `issuedBy`, `awardNumber`, `honorsCode`, `supersedesVersionId`, `supersededByVersionId`, `correlationId`

**Not exposed:** status labels, `isGraduated`, StudentStatus, SoD flags, publication state.

---

## 8. Repository contract

```text
GraduationReadRepositoryInterface::findGraduationAward(schoolId, enrollmentId): ?GraduationAwardDTO
```

Single SELECT with explicit columns; LEFT JOIN on pointer only; `WHERE school_id = ? AND enrollment_id = ?`.

---

## 9. Security / RLS

| Layer | Behavior |
|-------|----------|
| Application | `GraduationAuthorityPort::assertSchoolMatches(schoolId)` before read |
| SQL | school + enrollment scope |
| PostgreSQL | Existing ENABLE + FORCE RLS (unchanged) |
| Test | `PostgreSqlRlsActor` / `sis_rls_tester` NOBYPASSRLS |

School B actor: School A award invisible; repository lookup for School A identity returns `null`.

---

## 10. Performance

| Item | Status |
|------|--------|
| Access path | UNIQUE `(school_id, enrollment_id)` |
| Version resolve | PK via pointer FK — no version-table scan fallback |
| New index | **None** |
| SELECT * | **Avoided** |
| N+1 | **Avoided** (one query) |

---

## 11. Tests

### Unit — `GetGraduationAwardHandlerTest`

| # | Case | Result |
|---|------|--------|
| 1 | Award + pointed version | PASS |
| 2 | Null when no award | PASS |
| 3 | Null pointer → null version | PASS |
| 4 | Revoked pointed facts | PASS |
| 5 | No fallback when pointer null (DTO) | PASS |
| 6 | No replace revoked with other (DTO) | PASS |
| 7 | Raw lifecycle / is_current_issued | PASS |
| 8 | No StudentStatus / SoD fields | PASS |
| 9 | School mismatch before repo | PASS |

**9/9 PASS**

### PostgreSQL — `GetGraduationAwardPostgreSqlTest`  
(`phpunit.database-pgsql.xml` → `sis_test`)

| # | Case | Result |
|---|------|--------|
| 1 | School A reads School A award | PASS |
| 2 | School B handler authority deny | PASS |
| 3 | RLS School B cannot see / repo null | PASS |
| 4 | Pointer resolves exactly | PASS |
| 5 | NULL pointer → award + null version | PASS |
| 6 | **Negative:** null pointer does **not** fallback to `is_current_issued` | PASS |
| 7 | **Negative:** stale revoked pointer returns v1, not current v2 | PASS |
| 8 | Multiple versions + null pointer → no arbitrary select | PASS |
| 9 | No award → null | PASS |

**9/9 PASS**

---

## 12. Architecture validation

```text
php artisan architecture:validate --fitness  → PASS
php artisan architecture:feature-check Graduation → PASS
```

---

## 13. Files changed / added

| Path | Action |
|------|--------|
| `app/Application/Graduation/Queries/GetGraduationAwardQuery.php` | Added |
| `app/Application/Graduation/Queries/GetGraduationAwardHandler.php` | Added |
| `app/Application/Graduation/DTOs/GraduationAwardDTO.php` | Added |
| `app/Application/Graduation/DTOs/GraduationAwardVersionDTO.php` | Added |
| `app/Application/Graduation/Contracts/GraduationReadRepositoryInterface.php` | Extended |
| `app/Infrastructure/Persistence/Graduation/EloquentGraduationReadRepository.php` | Extended |
| `tests/Unit/Graduation/GetGraduationAwardHandlerTest.php` | Added |
| `tests/Feature/Database/PostgreSql/GetGraduationAwardPostgreSqlTest.php` | Added |
| `.cursor/architecture/features/Graduation.md` | Updated (query row) |
| `.cursor/database/phase-3c-19/04-GET-GRADUATION-AWARD-GATE.md` | Added |

---

## 14. Files not changed

```text
Permission.php · routes · controllers · policies
migrations · schema · indexes · RLS policies
IssueAward* · RevokeAward* · PublishAward*
StudentStatus · SoD · outbox · idempotency
GetOutcomeHistory · GetProvenance · EvidenceSet
```

---

## 15. Mutation check

```text
DATABASE MUTATION: NONE
HTTP: NONE
PERMISSIONS: NONE
WRITE PATH: NONE
RLS: NONE
INDEX: NONE
```

---

## 16. Findings

| ID | Finding | Disposition |
|----|---------|-------------|
| F-01 | 04 audit LATERAL fallback withdrawn by 04A | Implemented pointer-only; negative tests lock decision |
| F-02 | Post-revoke pointer may disagree with `is_current_issued` | Accepted; return pointed raw facts |
| F-03 | Award query returns null (not CompletionOutcomeNotFound) | Intentional award-centric contract |

---

## 17. Deferred items

```text
GetOutcomeHistory / full version history
GetProvenance / revocation_records detail
HTTP / HD-31-G permissions
Write-path pointer clear on revoke (out of scope)
Separate "current issued by flag" read field (04A optional — not locked)
```

---

## 18. Final gate decision

### Pre-declaration checklist

```text
[x] Award lookup is school_id + enrollment_id
[x] Award cardinality is 0..1
[x] Version cardinality is 0..N
[x] current_issued_version_id is the ONLY version selector
[x] NULL pointer returns award + null version
[x] No is_current_issued fallback
[x] No version_no fallback
[x] No awarded_at fallback
[x] No MAX(id)
[x] No MAX(created_at)
[x] No lateral fallback
[x] Revoked pointed version remains returned as raw facts
[x] No synthetic status labels
[x] No StudentStatus
[x] No SoD recalculation
[x] No HTTP
[x] No Permission changes
[x] No DB mutation
[x] No RLS mutation
[x] No index creation
[x] School isolation tested
[x] Negative pointer-null test passes
[x] Negative stale/revoked-pointer test passes
[x] Architecture gates pass
```

```text
PHASE 3C.19.4 FINAL GATE: PASS
```

**Risk:** LOW  
**Human approval required for next unit:** YES (HTTP / next query not authorized)

---

```text
PHASE 3C.19.4

UNIT:
GetGraduationAwardQuery

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

NEXT UNIT:
NOT AUTHORIZED

FINAL:
STOP
```
