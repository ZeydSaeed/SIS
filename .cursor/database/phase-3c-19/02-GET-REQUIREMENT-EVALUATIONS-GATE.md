# PHASE 3C.19.2
# GET REQUIREMENT EVALUATIONS QUERY GATE

**Date:** 2026-09-11  
**Unit:** UNIT-3C19-02 `GetRequirementEvaluationsQuery`  
**Mode:** IMPLEMENTATION — ONE UNIT ONLY  
**Predecessor:** Phase 3C.19.1 GetCompletionStatusQuery = PASS  

---

## 1. Scope

```text
GetRequirementEvaluationsQuery
+ GetRequirementEvaluationsHandler
+ GraduationReadRepositoryInterface::findRequirementEvaluations
+ EloquentGraduationReadRepository (read method)
+ RequirementEvaluationsDTO / RequirementEvaluationItemDTO
+ Unit + PostgreSQL school/RLS tests
+ Feature contract + this gate report
```

---

## 2. Contract

### Input

```text
schoolId + enrollmentId
```

### Output

`RequirementEvaluationsDTO`:

- Outcome identity + preferred `completion_outcome_version_id` (nullable)
- `evaluations[]` of raw `requirement_evaluations` facts

### Not-found

Missing CompletionOutcome → `CompletionOutcomeNotFoundException` (same as 3C.19.1).

### Empty evaluations

Outcome exists (even without versions) → DTO with `evaluations = []` (and `completionOutcomeVersionId = null` when no version).

### School isolation

Authority `assertSchoolMatches` + SQL `school_id` scope + RLS/FORCE RLS.

---

## 3. Contract field classification

| Field | Source | Classification |
|-------|--------|----------------|
| schoolId | `completion_outcomes.school_id` | AUTHORITATIVE |
| enrollmentId | `completion_outcomes.enrollment_id` | AUTHORITATIVE |
| completionOutcomeId | `completion_outcomes.id` | AUTHORITATIVE |
| completionOutcomeVersionId | preferred version `id` | AUTHORITATIVE row / ARCHITECTURALLY DEFINED selection (3C.19.1) |
| evaluations[].id | `requirement_evaluations.id` | AUTHORITATIVE |
| evaluations[].schoolId | `requirement_evaluations.school_id` | AUTHORITATIVE |
| evaluations[].completionOutcomeVersionId | `requirement_evaluations.completion_outcome_version_id` | AUTHORITATIVE |
| evaluations[].requirementDefinitionVersionId | `requirement_evaluations.requirement_definition_version_id` | AUTHORITATIVE |
| evaluations[].resultStatus | `requirement_evaluations.result_status` | AUTHORITATIVE (raw SMALLINT) |
| evaluations[].evaluatedAt | `requirement_evaluations.evaluated_at` | AUTHORITATIVE |
| evaluations[].notesRef | `requirement_evaluations.notes_ref` | AUTHORITATIVE |

**Not implemented (UNKNOWN/INVENTED/out of bound):** status labels, GPA, eligibility decisions, EvidenceSet, approvals, awards, StudentStatus, requirement definition names.

---

## 4. Authoritative sources

| Source | Role |
|--------|------|
| 3C.19.1 gate | Version preference precedent |
| 3C.12 schema | `requirement_evaluations` FK to version |
| 3C.9 | Query name |
| 3C.18 | Read-path sequencing |
| HD-39 / DL-022 | Identity / no StudentStatus |

---

## 5. Version/provenance rule

Evaluations are keyed by `completion_outcome_version_id` (schema FK — AUTHORITATIVE).

Preferred version selection (**same as 3C.19.1**, not a new policy):

```text
ORDER BY is_current_official DESC, version_no DESC
LIMIT 1
```

Only evaluations for that version are returned. Older versions’ rows are excluded by join, not by inventing institutional “current” meaning beyond the locked architectural preference.

---

## 6. Query flow

```text
GetRequirementEvaluationsQuery
  → Handler
      → authority.assertSchoolMatches
      → findRequirementEvaluations(schoolId, enrollmentId)
          → single SQL: outcome + LATERAL preferred version + LEFT JOIN evaluations
      → null → CompletionOutcomeNotFoundException
      → RequirementEvaluationsDTO
```

---

## 7. Repository design

Extended existing `GraduationReadRepositoryInterface` with one method only:

```text
findRequirementEvaluations(int $schoolId, int $enrollmentId): ?RequirementEvaluationsDTO
```

No giant repository surface; no write methods.

---

## 8. DTO

Immutable `RequirementEvaluationsDTO` + `RequirementEvaluationItemDTO`. No ORM leakage. No policy computation.

---

## 9. School isolation

| Layer | Mechanism |
|-------|-----------|
| Application | `GraduationAuthorityPort::assertSchoolMatches` |
| SQL | `WHERE o.school_id = ? AND o.enrollment_id = ?` + join `re.school_id = o.school_id` |
| PostgreSQL | ENABLE + FORCE RLS |

---

## 10. PostgreSQL RLS verification

`rls_actor_under_school_b_cannot_see_school_a_requirement_evaluations`:

- Seeds School A + B evaluations  
- `PostgreSqlRlsActor` (`sis_rls_tester` NOBYPASSRLS)  
- GUC = School B → School A evaluation ids invisible  
- Repository find for School A identity → null  
- School B identity → 2 evaluations  

Uses existing graduation grants from 3C.19.1 (no new production grants).

---

## 11. Tests

| Suite | Result |
|-------|--------|
| Unit `GetRequirementEvaluationsHandlerTest` | 5/5 PASS |
| PG `GetRequirementEvaluationsPostgreSqlTest` | 6/6 PASS |

Covers: valid multi-row mapping, official-version provenance, empty evaluations, missing outcome, school mismatch, RLS cross-school, raw `result_status` (no labels).

---

## 12. Architecture validation

```text
php artisan architecture:validate --fitness → PASS
php artisan architecture:feature-check Graduation → PASS
```

---

## 13. Security validation

- No HTTP / Permission.php / policies  
- Proof = SchoolContext authority + SQL scope + NOBYPASSRLS RLS test  

---

## 14. Performance observations

| Item | Value |
|------|-------|
| Logical SQL queries | 1 |
| N+1 | None |
| SELECT * | Avoided |
| Access path | Outcome UNIQUE `(school_id, enrollment_id)` + `requirement_evaluations_version_status_idx` |
| New index required | **NO** — no INDEX GAP |

No production performance claims.

---

## 15. Files changed

| Path | Action |
|------|--------|
| `app/Application/Graduation/Queries/GetRequirementEvaluationsQuery.php` | Added |
| `app/Application/Graduation/Queries/GetRequirementEvaluationsHandler.php` | Added |
| `app/Application/Graduation/DTOs/RequirementEvaluationsDTO.php` | Added |
| `app/Application/Graduation/DTOs/RequirementEvaluationItemDTO.php` | Added |
| `app/Application/Graduation/Contracts/GraduationReadRepositoryInterface.php` | Extended |
| `app/Infrastructure/Persistence/Graduation/EloquentGraduationReadRepository.php` | Extended |
| `tests/Unit/Graduation/GetRequirementEvaluationsHandlerTest.php` | Added |
| `tests/Feature/Database/PostgreSql/GetRequirementEvaluationsPostgreSqlTest.php` | Added |
| `.cursor/architecture/features/Graduation.md` | Query marked ✅ |
| `.cursor/database/phase-3c-19/02-GET-REQUIREMENT-EVALUATIONS-GATE.md` | This report |

---

## 16. Files not changed

Write handlers · Permission.php · routes · controllers · migrations · RLS policies · StudentStatus · EvidenceSet · approvals/awards · other Graduation queries · `PostgreSqlRlsActor` (already granted graduation in 3C.19.1).

---

## 17. Findings

| ID | Finding | Action |
|----|---------|--------|
| F-01 | Version preference reused from 3C.19.1 | Documented as architectural continuity |
| F-02 | EvidenceSet not joined | Intentional — write path does not populate; out of contract |

---

## 18. Deferred items

GetGraduationApproval · GetGraduationAward · GetOutcomeHistory · GetProvenance · HTTP · HD-31-G · Evidence reads · status labels.

---

## 19. Mutation check

```text
HTTP CHANGES: NONE
ROUTE CHANGES: NONE
CONTROLLER CHANGES: NONE
PERMISSION CHANGES: NONE

MIGRATIONS: NONE
SCHEMA CHANGES: NONE
INDEX CHANGES: NONE
RLS POLICY CHANGES: NONE

PRODUCTION DATA MUTATION: NONE

WRITE HANDLER CHANGES: NONE
StudentStatus CHANGES: NONE
PublishAward CHANGES: NONE
EvidenceSet WRITE CHANGES: NONE
APPROVAL CHANGES: NONE
AWARD CHANGES: NONE
```

---

## 20. Gate decision

```text
PHASE 3C.19.2 FINAL GATE: PASS
```

**STOP.** Do not implement the next Graduation query without new human authorization.
