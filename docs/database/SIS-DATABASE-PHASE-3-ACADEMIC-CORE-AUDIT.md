# DATABASE PHASE 3 — ACADEMIC CORE  
# ARCHITECTURE & DEPENDENCY AUDIT

**Document type:** AUDIT + PLAN ONLY  
**Date:** 2026-09-10  
**Database inspected (read-only):** `sis` @ PostgreSQL 18.2  
**Blueprint SSOT:** 87 objects (unchanged by this document)  
**Prior gates:** Phase 2 PASS WITH CONDITIONS · Phase 2 Remediation PASS WITH CONDITIONS  

```text
THIS DOCUMENT DOES NOT AUTHORIZE IMPLEMENTATION.
NO DDL · NO MIGRATIONS · NO RLS CHANGES · NO INDEX CHANGES
```

---

## 1. Executive Summary

The live SIS foundation through **Admission + Enrollment + Attendance** is in place. Schemas `exams` and `results` exist but are **empty** (0 tables). Blueprint already defines **5 exams + 3 results** tables, including partitioned `exams.student_grades` (P0).

**Recommendation:** Do **not** implement Exams + Grades + Results + Transcripts in a single wave.

Adopt a **staged Option C**:

| Stage | Name | Intent |
|-------|------|--------|
| **3A** | Assessment / Exam foundation | Types, exams, sessions, exam enrollments + RLS design |
| **3B** | Authoritative grades | `student_grades` (partitioned) + immutability/correction rules |
| **3C** | Derived results | `term_results`, `annual_results` (calculated projections) |
| **3D** | Transcript artifacts | `transcripts` + generation/idempotency |

**Authoritative grade source of truth:** `exams.student_grades` (OLTP).  
**Derived:** `results.term_results` / `results.annual_results`.  
**Artifact:** `results.transcripts` (file metadata + number).

```text
PHASE 3 PLAN READY FOR HUMAN APPROVAL
```

---

## 2. Current Database State (read-only)

| Item | Live value |
|------|------------|
| Engine | PostgreSQL 18.2 |
| Database | `sis` |
| `exams` tables | **0** |
| `results` tables | **0** |
| `enrollment` | 4 tables LIVE |
| `attendance` | sessions, records (partitioned), records_default, daily_section_summary |
| `curriculum` | subjects, curricula, curriculum_subjects (**prerequisites** missing) |
| `academic` | 5 tables LIVE (years, terms, grade_levels, holidays, settings) |
| `students` | 3 tables LIVE (documents missing) |
| `admission` | 3 tables LIVE + FORCE RLS |
| RLS enabled | admission (FORCE), enrollments, attendance.records |
| MVs | 3 reporting MVs (no grade/result MVs yet) |
| Destructive-test guard | Active (`sis` blocked; `sis_test` / `:memory:` allowed) |

---

## 3. Existing Academic Entities

### LIVE (implemented)

| Entity | Classification | Location |
|--------|----------------|----------|
| School | CORE | `organization.schools` |
| Academic year / term | CORE | `academic.*` |
| Student | CORE | `students.students` |
| Enrollment | CORE | `enrollment.enrollments` (+ classes/sections) |
| Subject | CORE | `curriculum.subjects` (`max_grade`/`pass_grade` already modeled) |
| Attendance session/record | CORE / EVENT | `attendance.*` |
| Application | CORE (pre-student) | `admission.applications` |

### BLUEPRINT ONLY (not migrated)

| Entity | Schema.table |
|--------|----------------|
| Exam type | `exams.exam_types` |
| Exam | `exams.exams` |
| Exam session | `exams.exam_sessions` |
| Exam enrollment | `exams.exam_enrollments` |
| Student grade | `exams.student_grades` |
| Term result | `results.term_results` |
| Annual result | `results.annual_results` |
| Transcript | `results.transcripts` |

### Application / Domain code

| Context | Status |
|---------|--------|
| Student / Enrollment | Clean Architecture LIVE |
| Admission | Status VOs only (DB LIVE) |
| Exams / Grades / Results / Transcript | **None** |

---

## 4. Dependency Graph

```text
organization.schools
        │
academic.academic_years ── academic.terms
        │
students.students
        │
enrollment.classes → sections → enrollments ←── curriculum.subjects
        │                              │
        │                              ▼
        │                    enrollment.enrollment_subjects
        │
        ├──────────────────► attendance.sessions / records
        │
        └──────────────────► exams.exams → exam_sessions → exam_enrollments
                                      │
                                      ▼
                            exams.student_grades   ★ AUTHORITATIVE SCORE
                                      │
                                      ▼
                            results.term_results   (DERIVED)
                            results.annual_results (DERIVED)
                                      │
                                      ▼
                            results.transcripts    (ARTIFACT / PROJECTION)
```

### Entity classification

| Concept | Type |
|---------|------|
| School, Year, Term, Student, Enrollment, Subject, Exam, Exam Session | CORE ENTITY |
| Exam type, grade letter, pass/fail, enrollment status | VALUE / ENUM (SMALLINT + Domain VO) |
| exam_enrollments, enrollment_subjects | RELATION |
| Grade correction / finalization / security audit / outbox | EVENT / HISTORY |
| term_results, annual_results, ranks, GPA | DERIVED DATA |
| transcripts, grade MVs (future) | REPORTING PROJECTION |

---

## 5. Proposed Domain Model (design only)

Reuse blueprint structure; refine semantics before implementation:

```text
ExamType (weight) 
  → Exam (school, year, term, type, window, status)
    → ExamSession (subject, schedule, room, max/pass)
      → ExamEnrollment (enrollment seating/eligibility)
      → StudentGrade (authoritative score per session+student)
```

**Do not invent** parallel `assessment_results` / `scores` tables unless a later ADR proves blueprint insufficient.

**Vocational note:** Practical vs theory can be represented via `curriculum.subjects.subject_type` + multiple exam sessions/types — not a separate grade ledger.

---

## 6. Exam Model Recommendation

Adopt blueprint `exams.*` with these design locks at implementation time:

| Decision | Recommendation |
|----------|----------------|
| Scope of an Exam | School + academic year + term + type |
| Session | One subject sitting (date/time/room) |
| Who may sit | Via `exam_enrollments.enrollment_id` (not free-floating students) |
| Status lifecycle | SMALLINT VO: Draft → Scheduled → InProgress → Closed → Cancelled |
| Room | Optional FK to `organization.rooms` |
| Weight | On `exam_types.weight_percentage` (0–100 CHECK already in blueprint) |

**Deferred:** separate “coursework assignment” subsystem beyond exam types — YAGNI until vocational continuous assessment requires it.

---

## 7. Grade Model Recommendation

### Authoritative source of truth

```text
exams.student_grades
```

One row = one student’s score for one exam session (UNIQUE `exam_session_id + student_id` per blueprint).

Denormalized FKs (`student_id`, `enrollment_id`, `academic_year_id`, `subject_id`) are justified for query locality and partition key — same pattern as `attendance.records`.

### Competing sources — FORBIDDEN

| Store | Role |
|-------|------|
| `student_grades` | **Authoritative** exam score |
| `term_results` / `annual_results` | **Derived** aggregates only (recomputable) |
| UI / Excel / JSON blob | Never authoritative |
| Teacher personal notes | Out of scope |

### Proposed column enrichments (PROPOSED — not blueprint change yet)

Document for future ADR / blueprint update before 3B implementation:

| Column | Why |
|--------|-----|
| `school_id` | Direct RLS (match attendance/enrollment denorm) |
| `status` | Draft / Posted / Finalized / Voided |
| `version` or correction linkage | Immutability model |
| `max_grade` snapshot | Session defaults may change later |

Letter/GPA scales: derive from school settings / subject pass rules; do not hard-code multiple ledgers.

---

## 8. Grade Immutability / Correction Model

### Allowed operations

| Operation | Allowed? | Mechanism |
|-----------|----------|-----------|
| INSERT (first entry) | YES | Command + idempotency + outbox |
| UPDATE while Draft/Posted (pre-finalize) | YES (controlled) | Same row OR revision policy (choose one ADR) |
| FINALIZE / LOCK | YES | Status → Finalized; block casual edit |
| CORRECT after finalize | YES | **Correction record** + new authoritative version; never silent overwrite |
| VOID | YES | Status Voided; retain row |
| REOPEN | CONTROLLED | Elevated permission + audit + reason |
| HARD DELETE | **NO** | Forbidden for authoritative grades |

### Recommended correction pattern (design)

```text
Option preferred: VOID + INSERT replacement version
  linked by correction_of_grade_id (nullable FK self)
  + security_audit_logs / outbox event GradeCorrected
```

Avoid dual-write into a separate `grade_history` table **and** mutable current row without clear SSOT. Prefer:

- Current authoritative row set (non-void) unique per `(exam_session_id, student_id)` via partial unique index `WHERE status <> voided`  
  **or**  
- Append-only versions with `is_current` partial unique  

Pick one in Stage 3B ADR before coding.

**Default principle:** `NO HARD DELETE OF AUTHORITATIVE GRADES`.

---

## 9. RLS Model

### Pattern to extend

| GUC | `app.current_school_id` (existing) |
| Middleware | `SchoolContextMiddleware` |
| Fail-closed | Non-empty GUC AND `school_id` match |
| Admission lesson | **FORCE ROW LEVEL SECURITY** required for meaningful owner enforcement |

### Per proposed table

| Table | `school_id` | RLS at implement | FORCE |
|-------|-------------|------------------|-------|
| `exams.exams` | Direct (blueprint) | YES | YES (recommended) |
| `exams.exam_sessions` | Derived via exam **or** denorm | Prefer denorm for policy simplicity | YES |
| `exams.exam_enrollments` | Derived via session→exam | Policy EXISTS chain or denorm | YES |
| `exams.student_grades` | **Add direct denorm** (proposed) | YES | YES |
| `results.term_results` | Via enrollment or denorm | YES | YES |
| `results.annual_results` | Via enrollment or denorm | YES | YES |
| `results.transcripts` | Denorm `school_id` proposed | YES | YES |
| `exams.exam_types` | Reference — usually global | NO / optional school-scoped later | — |

**Do not weaken** existing enrollment/attendance/admission policies.

**Service roles:** controlled `BYPASSRLS` only for explicit maintenance roles — never app login role; use SET ROLE patterns proven in Phase 2 remediation tests.

---

## 10. Security Matrix

| Scenario | Decision |
|----------|----------|
| School A user → School A grades | **ALLOW** (RBAC + RLS) |
| School A user → School B grades | **DENY** |
| School B user → School A grades | **DENY** |
| No school context → grades | **DENY** (fail-closed) |
| Invalid school context → grades | **DENY** |
| Admin/system operation → grades | **CONTROLLED SYSTEM ACCESS** (elevated role + audit; not anonymous bypass) |
| Background job → grades | **CONTROLLED** (set GUC per school batch or privileged job role with audit) |
| Reporting process → grades | **CONTROLLED** (read replica / MV / aggregated results with school scope) |

---

## 11. Index Strategy (proposed — not created)

| Index | Table | Query justification |
|-------|-------|---------------------|
| UNIQUE `(exam_session_id, student_id)` [+ status predicate if versioning] | student_grades | One active grade per sitting |
| `(student_id, academic_year_id)` | student_grades | Transcript / student year view |
| `(school_id, academic_year_id, subject_id)` | student_grades | Class/subject entry grids |
| `(enrollment_id)` | student_grades | Enrollment academic profile |
| `(academic_year_id, school_id)` | exams | School year exam list |
| `(exam_id)` / `(subject_id, session_date)` | exam_sessions | Session scheduling |
| UNIQUE `(exam_session_id, enrollment_id)` | exam_enrollments | Seating integrity |
| UNIQUE `(enrollment_id, term_id, subject_id)` | term_results | Derived uniqueness |
| UNIQUE `(enrollment_id)` | annual_results | One annual rollup |
| UNIQUE `transcript_number` + `(student_id, academic_year_id)` | transcripts | Artifact lookup |

**Reject:** indexing every FK blindly; GIN/GiST/BRIN on grades without evidence.

---

## 12. Partitioning Decision

| Table | Decision | Justification |
|-------|----------|---------------|
| `exams.student_grades` | **PARTITION NOW** (at first create) | Blueprint + ADR-002 P0; ~2.7M rows/year baseline; LIST(`academic_year_id`); matches attendance pattern |
| `attendance.records` | Already partitioned | Keep |
| `results.term_results` | **PARTITION LATER** | Lower volume; revisit with measured growth |
| `results.annual_results` | **NO PARTITION** | ~1 row/enrollment/year |
| `results.transcripts` | **NO PARTITION** | Artifact metadata, low volume |
| Exam header/session tables | **NO PARTITION** | Small reference/operational |

**Caution:** UNIQUE and FK design must include partition key (`academic_year_id`) on `student_grades` — already in blueprint PK strategy notes for attendance.

---

## 13. Normalization Analysis

| Form | Status |
|------|--------|
| 1NF | Satisfied by relational blueprint (atomic grade columns) |
| 2NF | Grades keyed by session+student; avoid embedding student name |
| 3NF | Denormalized `school_id` / `academic_year_id` on facts = **controlled denorm** for RLS/partition (same as attendance) — document as intentional |

**Avoid:** JSON score maps; duplicating subject names on grade rows; treating `term_results` as manually editable masters.

---

## 14. CQRS Decision

| Area | Recommendation |
|------|----------------|
| Grade entry / correction / finalize | **SIMPLE CRUD-style Commands** (Clean Architecture handlers) + outbox |
| Term/annual calculation | **CQRS + PROJECTION** — recompute into `results.*` via jobs |
| Transcript generation | **CQRS + PROJECTION** — async job + idempotency key |
| Teacher entry UI reads | Read models from `student_grades` (same DB; no extra bus) |
| Directorate analytics | Existing MV pattern — add grade MVs only when queries prove need |

Do **not** introduce Kafka/event-sourcing complexity.

---

## 15. Audit / Outbox / Idempotency

Reuse LIVE infrastructure:

| Store | Use for grades |
|-------|----------------|
| `audit.idempotency_keys` | Grade batch entry, finalize, correct, transcript generate |
| `audit.outbox_messages` | `GradeRecorded`, `GradeFinalized`, `GradeCorrected`, `GradeVoided`, `TranscriptGenerated` |
| `security.security_audit_logs` | Actor/school/result for sensitive mutations |

**Domain audit table `audit.audit_logs`:** still blueprint-only — optional Stage 3B/3E; do not block grades on it if security audit + outbox cover compliance initially.

---

## 16. History Strategy

| Proposed entity | Needed? | Rationale |
|-----------------|---------|-----------|
| Separate `grade_history` | **Maybe** | Only if versioning-in-place is chosen; otherwise VOID+replace + audit suffices |
| `exam_attempt_history` | **REJECTED** for v1 | `exam_enrollments` + grades cover attempt outcome |
| `assessment_history` | **REJECTED** | No parallel assessment ledger in blueprint |
| `finalization_history` | **DEFERRED** | Capture via outbox + security audit; table only if regulators require queryable finalization log |

---

## 17. Retention Strategy

| Record | Policy |
|--------|--------|
| `student_grades` (incl. voided) | **Never hard-delete**; online for active+recent years |
| `term_results` / `annual_results` | Rebuildable but keep online for performance; archive cold years later |
| `transcripts` metadata | Retain; files in object storage |
| Exam definitions | Soft status; retain for historical interpretation of grades |
| Derived MVs | Rebuildable |

**Archive later** via `archive` schema / partition detach — requires separate ADR.

---

## 18. PostgreSQL-Specific Decisions

| Feature | Classification | Notes |
|---------|----------------|-------|
| BIGINT IDENTITY PK | Portable SQL | Keep (ADR-003 / ADR-020) |
| LIST partition by academic_year_id | PostgreSQL-specific justified | Performance + retention |
| RLS + GUC `app.current_school_id` | PostgreSQL-specific justified | Security defense-in-depth |
| FORCE RLS | PostgreSQL-specific justified | Close owner bypass (Admission precedent) |
| Partial unique indexes | PostgreSQL-specific justified | Active grade uniqueness with voided rows |
| EXCLUDE constraints | Avoidable for v1 | Use UNIQUE + status instead unless conflicts require |

---

## 19. Future DB Migration Considerations

- Keep Domain/Application free of PG SQL.  
- Isolate RLS/partition DDL in migrations labeled PostgreSQL-only (existing SchemaHelper pattern).  
- If engine migration ever required: PKs/FKs/CHECKs port; RLS must be re-implemented in app middleware + DB equivalent.  
- Do not invent UUID PK churn for “portability.”

---

## 20. Blueprint Impact

```text
Current blueprint objects     = 87
Proposed Phase 3A–3D tables   = 8 (already IN blueprint: 5 exams + 3 results)
Net NEW blueprint tables      = 0 (if implementing blueprint as-is)
```

| Category | Items |
|----------|-------|
| **EXISTING (LIVE)** | org, academic, students, enrollment, curriculum (partial), attendance, admission, security/audit support |
| **PROPOSED (implement from blueprint)** | `exam_types`, `exams`, `exam_sessions`, `exam_enrollments`, `student_grades`, `term_results`, `annual_results`, `transcripts` |
| **PROPOSED (column/ADR enrichments)** | `school_id` on grades/transcripts; grade `status`/correction link; FORCE RLS |
| **DEFERRED** | generic assignment subsystem; grade_history table; promotion/transfer/graduation; grade MVs beyond existing 3 |
| **REJECTED (v1)** | Parallel score JSON store; hard deletes; UUID grade PKs; big-bang 3A–3D single migration wave |

**Do not modify blueprint in this audit phase.** Column enrichments require human-approved blueprint update **before** Stage 3B coding.

---

## 21. Risk Register

| Risk | Severity | Probability | Mitigation |
|------|----------|-------------|----------------|
| Grade hard deletion | CRITICAL | Medium without controls | Forbid DELETE; status void; tests |
| Cross-school access | CRITICAL | Medium | Direct `school_id` + FORCE RLS + fail-closed GUC |
| Mutation after finalization | CRITICAL | High | Status lock + correction workflow + audit |
| Incorrect aggregation | HIGH | Medium | Results as derived jobs; reconcile checks |
| Duplicate grade source | HIGH | Medium | SSOT = `student_grades` only |
| Poor indexing | HIGH | Medium | Query-justified indexes only; EXPLAIN in gate |
| Premature partitioning of small tables | MEDIUM | Medium | Partition grades only at create; delay results |
| Excessive CQRS complexity | MEDIUM | Medium | Commands for writes; projections only for results/transcripts |
| Future schema evolution | HIGH | High | Additive migrations; ADR for correction model |
| Superuser RLS bypass in tests/ops | HIGH | High (seen in Phase 2) | FORCE RLS + non-bypass role for tests |
| Single-wave Exams+Grades+Results | HIGH | High if Option A/B chosen | Prefer staged Option C |
| RefreshDatabase against `sis` | CRITICAL | Low now | Guard CLOSED — keep enforced |

---

## 22. Proposed Phase 3 Stages

### Phase 3A — Exam Foundation

| Item | Content |
|------|---------|
| Scope | `exam_types`, `exams`, `exam_sessions`, `exam_enrollments` |
| Schema | `exams` (already empty) |
| Relationships | → schools, years, terms, subjects, rooms, enrollments |
| Constraints | FKs RESTRICT; UNIQUE seating; date CHECKs; weight CHECK |
| Indexes | As §11 for exam headers/sessions/enrollments |
| RLS | Enable + FORCE on school-scoped exam tables; denorm `school_id` on sessions/enrollments if needed |
| Tests | Structural + RLS (sis_test) + CHECK |
| Migration | Additive only; protected DB guard remains |
| Rollback | Drop new exam tables/policies only |
| Gate | Tables exist; RLS fail-closed; no grade tables yet |

### Phase 3B — Authoritative Grades

| Item | Content |
|------|---------|
| Scope | `student_grades` LIST partition by `academic_year_id` |
| Preconditions | 3A gate PASS; blueprint ADR for status/correction/`school_id` |
| Constraints | UNIQUE active grade; grade range CHECK; no hard delete |
| RLS | FORCE + school_id |
| Tests | Immutability, correction, cross-school DENY, partition pruning smoke |
| App | Handlers + idempotency + outbox events |
| Gate | SSOT documented; finalize/correct rules tested |

### Phase 3C — Derived Results

| Item | Content |
|------|---------|
| Scope | `term_results`, `annual_results` |
| Rule | Written only by calculation jobs — not teacher free-edit |
| Tests | Recompute idempotency; reconcile vs grades sample |
| Gate | No dual SSOT |

### Phase 3D — Transcripts

| Item | Content |
|------|---------|
| Scope | `results.transcripts` + storage metadata |
| Pattern | Queue job + idempotency (like certificates blueprint) |
| Gate | Artifact generation audited; file not in PostgreSQL |

**Optional 3E (later):** grade-related MVs (`mv_subject_results`, etc.) after measured dashboard need.

---

## 23. Gate Criteria (per stage)

```text
[ ] Scope matches approved stage only
[ ] Additive migrations only; sis protected from destructive tests
[ ] Blueprint updated BEFORE DDL if columns differ
[ ] PK BIGINT IDENTITY; RESTRICT FKs on history
[ ] RLS fail-closed + FORCE on school-scoped grade/exam facts
[ ] No hard delete of grades
[ ] sis_test PG tests PASS (not skipped-as-pass)
[ ] architecture:validate / security:validate considered
[ ] sis:verify-database read-only green on live
[ ] Stage Gate Report + HUMAN APPROVAL before next stage
```

---

## 24. Open Questions (need human answers before 3B)

1. Correction model: VOID+replace vs append-only versions?  
2. Confirm adding `school_id` + `status` to `student_grades` (blueprint enrichment)?  
3. Should `exam_sessions` / `exam_enrollments` denorm `school_id` or rely on join policies?  
4. Are continuous vocational practical assessments in-scope for 3A exam_types, or deferred?  
5. Must `audit.audit_logs` exist before first production grade entry, or is security_audit + outbox enough initially?  
6. Who may REOPEN finalized grades (role list)?  
7. Prefer Stage 3A alone as next implementation approval, or approve entire 3A–3D roadmap now with gates between?

---

## 25. Recommendation

```text
PHASE 3 PLAN READY FOR HUMAN APPROVAL
```

**Selected boundary:** Option C — staged Academic Assessment Core  
**Next implementable slice (when approved):** **Phase 3A — Exam Foundation only**  
**Do not start Grades until 3A gate PASS + correction/SSOT ADR answers.**

---

```text
HUMAN APPROVAL REQUIRED

This task was AUDIT + PLAN ONLY.

No Phase 3 database implementation was performed.
No Exams tables were created.
No Grades tables were created.
No ERP implementation was performed.
No production database was modified.
```
