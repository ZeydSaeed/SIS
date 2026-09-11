# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION — RLS RUNTIME VERIFICATION

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.1 — Exam Administration — PostgreSQL RLS Runtime Verification |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.1 — Exam Administration |
| **Document Type** | POST-IMPLEMENTATION RLS RUNTIME VERIFICATION |
| **Date** | 2026-09-11 |
| **Prior audit** | `16-PHASE-7.1-EXAM-ADMINISTRATION-IMPLEMENTATION-AUDIT.md` |
| **Mode** | VERIFICATION ONLY |

```text
HD-007 = GRANTED
CreateExam = COMPLETED
UpdateExam = COMPLETED
CancelExam = COMPLETED
```

---

## 2. Purpose

Close the PostgreSQL RLS behavioral-test condition left open in audit `16`.

This verification proves **database-level** school isolation for Phase 7.1 exam writer surfaces — not application-only `SchoolContext` / repository mocks.

---

## 3. Method

| Item | Detail |
|------|--------|
| Infrastructure | `PostgreSqlIntegrationTestCase` + disposable `sis_test` |
| Config | `phpunit.database-pgsql.xml` |
| RLS actor | `PostgreSqlRlsActor` (`sis_rls_tester` — **NOSUPERUSER / NOBYPASSRLS**) |
| GUC | `app.current_school_id` via `set_config` |
| Test file | `tests/Feature/Database/PostgreSql/ExamAdministrationRlsPostgreSqlTest.php` |

Superuser bypass was **not** used as proof of application behavior. Assertions run after `PostgreSqlRlsActor::become()`.

---

## 4. Verification Results

```text
PostgreSQL runtime verification = PASS
```

| Check | Result |
|-------|--------|
| Same-school mutation | **PASS** — CreateExam repository insert + Create/Update/Cancel handlers succeed under RLS actor with matching GUC |
| Cross-school mutation | **PASS** — foreign exam invisible; UPDATE under wrong school leaves row unchanged; handler cross-school path denied |
| Missing school context | **PASS** — SELECT counts = 0 on exams/sessions/enrollments/student_grades; INSERT fail-closed (savepoint-isolated) |
| FORCE RLS verification | **PASS** — catalog `relrowsecurity` + `relforcerowsecurity` true for `exams.exams`, `exams.exam_sessions`, `exams.exam_enrollments`, `exams.student_grades` |

### Tables covered

```text
exams.exams
exams.exam_sessions
exams.exam_enrollments
exams.student_grades
```

### Commands exercised under RLS actor

```text
CreateExam (handler + repository)
UpdateExam (handler)
CancelExam (handler, including session/enrollment cascade)
```

---

## 5. Executed Commands

```text
php vendor/bin/phpunit -c phpunit.database-pgsql.xml --filter ExamAdministrationRlsPostgreSqlTest
→ 6 passed, 0 failed

php artisan test tests/Unit/Exams tests/Feature/Exams/ExamAdministrationCommandTest.php tests/Feature/Security/ExamAdministrationAuthorizationTest.php
→ existing Phase 7.1 suite re-run after verification
```

---

## 6. Scope Audit

| Question | Answer |
|----------|--------|
| Phase 7.1 business implementation changed? | **NO** |
| Phase 7.2 changed? | **NO** |
| Query AuthZ changed? | **NO** |
| HTTP exposed? | **NO** |
| CompleteExam introduced? | **NO** |
| RLS weakened? | **NO** |
| Migrations / DROP / DISABLE RLS? | **NO** |

### Files added

* `tests/Feature/Database/PostgreSql/ExamAdministrationRlsPostgreSqlTest.php`
* `.cursor/database/phase-7.1/17-PHASE-7.1-EXAM-ADMINISTRATION-RLS-RUNTIME-VERIFICATION.md`

### Files modified (business)

* **NONE**

---

## 7. Defects Found

```text
NONE
```

No STOP for security defect. No automatic patch required.

---

## 8. Relationship to Audit 16

Audit `16` recorded:

```text
PASS WITH CONDITIONS
```

Condition: dedicated PostgreSQL RLS runtime tests for Phase 7.1 writers were not yet present.

This artifact closes that condition with genuine `sis_test` + non-superuser RLS actor evidence.

---

## 9. Final Verdict

```text
PASS

PostgreSQL runtime verification = PASS
Same-school mutation = PASS
Cross-school mutation = PASS
Missing school context = PASS
FORCE RLS verification = PASS

HTTP = BLOCKED
Query AuthZ = DEFERRED
Phase 7.2 = UNCHANGED

STOP — do not start Phase 7.2 / Query AuthZ / CompleteExam / HTTP exposure.
```
