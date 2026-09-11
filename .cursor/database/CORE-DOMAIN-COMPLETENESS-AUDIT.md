# SIS CORE DOMAIN COMPLETENESS AUDIT

**Date:** 2026-09-11  
**Mode:** AUDIT ONLY — READ ONLY  
**Implementation authorization:** NOT GRANTED  

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
CERTIFICATES PHASE 4.2: NOT AUTHORIZED
NO DDL AUTHORIZED
NO CODE CHANGES AUTHORIZED
NO MIGRATIONS AUTHORIZED
```

---

## 1. Executive Summary

SIS has a **strong closed downstream vertical** (Grades SSOT → Graduation/Completion → Certificates schema) while many **upstream operational domains** remain schema-only or partial (Organization, Academic calendar ops, Teachers, Guardians, Curriculum, Attendance CQRS, Exam scheduling CQRS, Results/Transcript, Promotion/Transfer).

```text
CURRENT STATE VERDICT:
  Downstream lifecycle (Grades → Graduation → Certificates DDL): STRONG
  Core daily operations (Attendance, Teachers, Sections ops): WEAK / FOUNDATION
  Results / Transcript / Promotion-Transfer: MISSING or DESIGN-ONLY

FINAL GATE: PASS WITH CONDITIONS
```

**Preservation decision (LOCKED for this audit):**

```text
Keep Graduation (Phase 3C closed surface).
Keep Certificates Phase 4.1 schema (six tables).
Do NOT roll back.
Correct the forward roadmap toward upstream completeness.
```

**Wrong-order assessment:** Implementing Graduation/Certificates before full Attendance/Teacher/Results CQRS created **roadmap debt**, not a **runtime integrity violation**. Enrollment + Grades + Award FKs are sufficient for the implemented Graduation/Certificate contracts. Sequencing was **largely harmless** relative to those contracts; the risk is **operational incompleteness**, not broken FKs.

---

## 2. Current System State

### What is LIVE and gated

| Area | State |
|------|--------|
| Organization / Academic / Students / Guardians / Teachers / Curriculum / Enrollment / Attendance schemas | LIVE tables (Phase 1–C foundation migrations) |
| Admission | LIVE schema + FORCE RLS; **no** Application CQRS |
| Exams foundation | LIVE + FORCE RLS |
| Student grades | LIVE + partition + FORCE RLS + full CQRS + HTTP |
| Graduation / Completion | LIVE 14 tables + writes + 5 reads; **PASS WITH CONDITIONS** |
| Certificates | LIVE 6 tables + FORCE RLS + reject-delete; **Phase 4.1 PASS**; **no** CQRS |

### Empty reserved schemas (no tables)

```text
results · promotion · transfers · documents · finance · communication · workflow
```

### Application CQRS contexts that exist

```text
Student · Enrollment · Exams(Grades) · Graduation · Intelligence · Observability
```

**Absent Application contexts:** Organization, Academic, Teachers, Guardians, Curriculum, Attendance, Admission, Certificates, Promotion, Transfers, Transcripts.

### Tenancy pattern maturity

| Pattern | Where |
|---------|--------|
| ENABLE + **FORCE** + fail-closed | Admission, Exams, Grades, Graduation, Certificates |
| ENABLE + fail-closed, **no FORCE** | `enrollment.enrollments`, `attendance.records` |
| App SchoolContext only | Students and most foundation tables |

---

## 3. Domain Completeness Matrix

| Domain | DB | RLS | Integrity | Domain Layer | CQRS | Tests | Business Rules | Status | Evidence |
| ------ | -- | --- | --------- | ------------ | ---- | ----- | -------------- | ------ | -------- |
| School / Organization | Yes | No | Strong FK/UNIQUE | Thin VOs | No | Foundation only | Codes/hierarchy | **FOUNDATION ONLY** | `100100_create_organization_tables` |
| Academic Year / Term | Yes | No | Strong | VOs only | No | Foundation | Years/terms/settings | **FOUNDATION ONLY** | `100200_create_academic_tables` |
| Students | Yes | No (app) | Strong UNIQUEs | Rich | Yes | Strong | Create/Update/Search | **PARTIAL** | `Application/Student`, `Domain/Student` |
| Teachers / Staff | Yes | No | Strong | No | No | Weak/none | Schema assignment only | **FOUNDATION ONLY** | `100700_create_teachers_tables` |
| Guardians / Contacts | Yes | No | Strong | No | No | None | Link table only | **FOUNDATION ONLY** | `100400_*` |
| Subjects / Courses | Yes | No | Strong | No | No | None | Subject≠Course unclear | **FOUNDATION ONLY** | `100600_*` + vocational |
| Classes / Sections | Yes | No | Strong | Infra records | No CRUD CQRS | Via enrollment | Homeroom + class/section | **PARTIAL** | `100500_*` |
| Teacher Assignment | Partial | No | Partial | No | No | None | `teacher_subjects` + homeroom | **FOUNDATION ONLY** | No section_teachers table |
| Enrollment | Yes | ENABLE no FORCE | Strong; no active-year UNIQUE | Rich | Yes | Strong | Enroll/Cancel/Place | **PARTIAL** | `Application/Enrollment` |
| Attendance / Absence | Yes + partition | ENABLE no FORCE | Strong session/student unique | No | No | Weak | Schema only | **FOUNDATION ONLY** | `100800_*` LIST partition |
| Exams | Yes | FORCE | Strong | Grade-centric | Grades only | Schema/RLS | No exam CRUD CQRS | **PARTIAL** | Phase 3A |
| Grades / Results | Grades YES; results NO | FORCE | Excellent | Rich | Full grade CQRS | Excellent | VOID+INSERT | **COMPLETE** (grades) / **MISSING** (results.*) | Phase 3B |
| Student Documents | Admission docs YES | FORCE | Metadata | No | No | Schema | No student_documents | **PARTIAL** / **MISSING** | Phase 2 admission |
| Transcript / History | No | — | — | Design docs | No | No | Deferred 3C.6/3D | **MISSING** | empty `results` |
| Completion | Yes | FORCE | Versioned | Services/events | Yes | Strong | Evaluate path | **PARTIAL** (closed w/ conditions) | Phase 3C |
| Graduation | Yes | FORCE | Award versions | Yes | Writes+reads | Strong | SoD, revoke, history | **PARTIAL** (closed w/ conditions) | Phase 3C.16–19 |
| Certificates | Yes (6) | FORCE | Award-version pin | No | No | Schema | DDL only | **FOUNDATION ONLY** | Phase 4.1 |
| Promotion / Transfer / Withdraw / Re-entry | No tables | — | — | Cancel only | Cancel only | Enrollment cancel | Status ACTIVE/CANCELLED | **MISSING** (+ cancel **PARTIAL**) | empty schemas |

---

## 4. Core Domain Scores (diagnostic /70)

| Domain | DB | Integrity | Security | Domain | CQRS | Tests | Ops | Total | Band |
|--------|---:|----------:|---------:|-------:|-----:|------:|----:|------:|------|
| School / Organization | 8 | 8 | 3 | 2 | 0 | 2 | 4 | **27** | RED |
| Academic structure | 8 | 8 | 3 | 2 | 0 | 2 | 4 | **27** | RED |
| Students | 8 | 8 | 6 | 8 | 8 | 9 | 7 | **54** | YELLOW |
| Teachers / Staff | 7 | 7 | 2 | 0 | 0 | 0 | 2 | **18** | RED |
| Guardians | 7 | 7 | 2 | 0 | 0 | 0 | 2 | **18** | RED |
| Subjects / Courses | 7 | 7 | 2 | 0 | 0 | 0 | 2 | **18** | RED |
| Classes / Sections | 8 | 8 | 2 | 3 | 2 | 4 | 4 | **31** | RED |
| Teacher Assignment | 4 | 5 | 2 | 0 | 0 | 0 | 2 | **13** | RED |
| Enrollment | 9 | 8 | 7 | 9 | 9 | 9 | 7 | **58** | YELLOW |
| Attendance | 9 | 8 | 6 | 0 | 0 | 2 | 5 | **30** | RED |
| Exams | 8 | 8 | 9 | 5 | 3 | 7 | 4 | **44** | RED |
| Grades | 10 | 10 | 10 | 9 | 10 | 10 | 8 | **67** | GREEN |
| Student Documents | 5 | 6 | 7 | 0 | 0 | 5 | 3 | **26** | RED |
| Transcript | 0 | 0 | 0 | 1 | 0 | 0 | 0 | **1** | RED |
| Completion | 9 | 9 | 9 | 7 | 9 | 9 | 6 | **58** | YELLOW |
| Graduation | 9 | 9 | 9 | 7 | 9 | 9 | 6 | **58** | YELLOW |
| Certificates | 9 | 9 | 9 | 0 | 0 | 7 | 3 | **37** | RED |
| Promo/Transfer/Re-entry | 0 | 0 | 0 | 2 | 2 | 3 | 1 | **8** | RED |

Scores do **not** override classifications (e.g. Certificates is FOUNDATION ONLY despite solid DDL score components).

---

## 5. Dependency Graph (evidence-corrected)

```text
organization.schools
        ↓
academic.academic_years / terms / grade_levels
        ↓
students.students (+ guardians.*)
        ↓
enrollment.classes → sections ← teachers (homeroom / teacher_subjects)
        ↓
enrollment.enrollments (+ enrollment_subjects)
        ├── attendance.sessions / records (partitioned)
        └── exams.exams → exam_sessions → exam_enrollments
                    ↓
            exams.student_grades  ←── SSOT scores
                    ↓
            [MISSING results.term/annual/transcript]
                    ↓
            graduation.completion_* → approvals → awards
                    ↓
            certificates.* (pin award_version)  ← schema only

promotion.* / transfers.*  ← EMPTY (would consume enrollment + grades)
```

**Notes:**

- Certificates **do not** depend on Attendance CQRS or Transcript tables.
- Graduation **does** depend on Enrollment identity + Award write path; evidence/evaluation content catalogs remain deferred.
- Grades do **not** require Results tables (Results are downstream projections).

---

## 6. Critical Gaps

| ID | Domain | Severity | Evidence | Consequence | Blocks prod? | Blocks downstream? | Recommended phase |
|----|--------|----------|----------|-------------|--------------|--------------------|-------------------|
| CORE-001 | Attendance | **P0** | Schema+partition; no Application CQRS | Cannot run daily school ops | **Yes** for ops go-live | No for Graduation/Certs | Attendance Application |
| CORE-002 | Teachers + Assignment | **P0** | Tables only; no CQRS | Cannot assign teachers operationally | **Yes** for ops | Soft for attendance/exams UX | Teachers Application |
| CORE-003 | Enrollment RLS FORCE | **P1** | ENABLE without FORCE | Owner/app role may bypass | Soft/medium | Soft | Security hardening |
| CORE-004 | Attendance RLS FORCE + sessions | **P1** | Only records ENABLE | Incomplete tenant force | Soft/medium | Soft | Security hardening |
| CORE-005 | Students RLS | **P1** | No DB RLS on students.* | Relies on app policies | Soft | Soft | Tenant hardening |
| CORE-006 | Exam scheduling CQRS | **P1** | Schema only for exam defs | Grades need seeded exams manually/SQL | Soft | Soft for grades | Exams Application |
| CORE-007 | Results / Transcript | **P1** | Empty `results` schema | No official term/annual/transcript artifacts | Soft for award path | **Yes** for Transcript product | Results Design→DDL |
| CORE-008 | Promotion / Transfer | **P1** | Empty schemas; status CANCELLED only | No year-end mobility | Soft | Soft for lifecycle | Lifecycle Design |
| CORE-009 | Certificates CQRS | **P2** | 4.1 schema PASS; no IssueCertificate | Cannot issue/verify | Soft | Self | Phase 4.2 (later auth) |
| CORE-010 | Graduation residuals | **P2** | HD-31-G, Publish, EvidenceSet, GetProvenance | Product/policy incomplete | Soft | Soft | Deferred / policy gates |
| CORE-011 | Guardians / Curriculum CQRS | **P2** | Foundation tables | Admin ops via SQL/seeds | Soft | Soft | Supporting Application |
| CORE-012 | Org/Academic CQRS | **P2** | Foundation tables | Calendar/org admin incomplete | Soft | Soft | Foundation Application |
| CORE-013 | Doc drift | **P3** | Blueprint totals; readiness “certs not LIVE” | Confusion | No | No | Doc sync |
| CORE-014 | Active enrollment UNIQUE | **P1** | App-only already-enrolled | Race duplicate enrollments | Soft | Soft | Enrollment integrity |

---

## 7. Security / RLS Findings

| Finding | Classification |
|---------|----------------|
| Mature FORCE RLS pattern on Admission/Exams/Grades/Graduation/Certificates | Strength |
| Enrollment + Attendance ENABLE without FORCE | Gap CORE-003/004 |
| Students/Teachers/Guardians/Curriculum/Org without RLS | Gap CORE-005/011/012 |
| SchoolContext middleware live | Strength |
| Certificates RLS tester grant list updated for tests | Strength |
| Graduation/Certificates reject-delete | Strength |
| Early enrollment/attendance policies historically fail-open then fixed | Historical; verify fail-closed remains |

---

## 8. Data Integrity Findings

| Finding | Classification |
|---------|----------------|
| Grades VOID+INSERT, partition, reject-delete | Excellent |
| Graduation versioning + award pointer-only reads | Excellent (closed) |
| Certificates award_version composite FK; no CASCADE | Excellent |
| No hard-delete official academic pattern in closed domains | Strength |
| Missing active `(student_id, academic_year_id)` enrollment unique | Gap CORE-014 |
| Blueprint still mixes stale graduation.records / old cert sketch in places | Doc CONFLICTED |
| Results/Promotion/Transfers empty vs blueprint sketches | CONFLICTED docs vs LIVE |

---

## 9. Scale Findings

| Domain | Scale posture |
|--------|---------------|
| Attendance.records | LIST partition by `academic_year_id` + default partition — **aligned** with 45K P0 intent; **no** Application batch write path yet |
| student_grades | LIST partitioned — aligned |
| Certificates | Metadata + async jobs designed; generation not implemented |
| Enrollment/Students | Appropriate for baseline; indexes via foundation |
| Results/Transcript | N/A (missing) |

**Do not** add indexes/partitions in this audit.

---

## 10. Current Roadmap Assessment

Historical effective order:

```text
Foundation schemas → Admission → Enrollment/Student CQRS → Exams → Grades
  → Graduation Application+Reads → Certificates DDL
```

Missed / deferred relative to WORK-PLAN Phase C–D:

```text
Attendance Application
Teachers Assignment Application
Exam scheduling Application
Results / Transcript
Promotion / Transfers
Certificates Application (4.2)
```

This is **acceptable debt** if the next roadmap prioritizes **ops prerequisites (P0)** before more downstream product features.

---

## 11. Corrected Recommended Roadmap

| Phase | Objective | Prerequisites | Scope | DB impact | App impact | Gate |
|-------|-----------|---------------|-------|-----------|------------|------|
| **R0** | Doc sync | None | Fix stale readiness/blueprint contradictions | Docs only | None | Audit close |
| **R1** | Attendance Application readiness | Attendance schema LIVE | Design Lock → CQRS issue/mark attendance | Likely none first | High | Design then unit auth |
| **R2** | Teachers + Assignment Application | Teachers tables LIVE | Design Lock → CQRS | Soft | High | Design then unit auth |
| **R3** | Enrollment/Attendance security FORCE | R1/R2 optional | FORCE RLS + tests | RLS ALTER | Soft | Security gate |
| **R4** | Exam scheduling Application | Exams schema LIVE | Create session/enroll CQRS | Soft | Medium | Unit auth |
| **R5** | Results / Transcript Design | Grades LIVE; HD locks | Design Lock (reuse 3C.6) | Later DDL | Later | Design only first |
| **R6** | Promotion / Transfer Design | Enrollment LIVE | Design Lock Candidate C | Later DDL | Later | Design only |
| **R7** | Certificates Phase 4.2 | 4.1 PASS | IssueCertificate CQRS | None | High | **Separate human auth** |
| **R8** | Graduation residuals | Policy HDs | Publish/Evidence/HTTP/Provenance as authorized | Soft | Soft | Per residual auth |

**Do not** start R7 (Certificates 4.2) or R8 automatically.

---

## 12. Graduation / Certificates Preservation Decision

```text
PRESERVE Graduation Phase 3C closed surface.
PRESERVE Certificates Phase 4.1 six-table schema.

NO redesign.
NO rollback.
NO Phase 4.2 without explicit APPROVED — IMPLEMENT PHASE 4 UNIT X.
NO Graduation remediation without separate authorization.
```

---

## 13. Priority Ranking

| Priority | Domains |
|----------|---------|
| **P0** | Attendance Application; Teachers/Assignment Application |
| **P1** | Enrollment/Attendance/Students RLS FORCE; Exam scheduling CQRS; Enrollment active UNIQUE; Results/Transcript Design; Promotion/Transfer Design |
| **P2** | Certificates 4.2; Guardians/Curriculum/Org CQRS; Graduation policy residuals |
| **P3** | Doc drift cleanup; finance/comms/workflow |

---

## 14. Next Action

```text
NEXT RECOMMENDED ACTION:
ATTENDANCE APPLICATION READINESS AUDIT + SCOPE LOCK
(design/audit only — no DDL/CQRS implementation until separately authorized)
```

Rationale: highest operational frequency, partitioned schema already ready, blocks daily production use, does not disturb Graduation/Certificates.

Alternative (if staffing prefers people ops first): Teachers Assignment Readiness Audit — still P0, secondary to daily attendance.

---

## 15. Final Gate

```text
CORE DOMAIN COMPLETENESS AUDIT: PASS WITH CONDITIONS

CONDITIONS:
  - Upstream ops domains (Attendance, Teachers) are FOUNDATION ONLY
  - Results/Transcript/Promotion-Transfer MISSING
  - Enrollment/Attendance lack FORCE RLS
  - Certificates remain schema-only until Phase 4.2 authorized
  - Graduation remains PASS WITH CONDITIONS (policy residuals)

IMPLEMENTATION AUTHORIZATION: NOT GRANTED
CERTIFICATES PHASE 4.2: NOT AUTHORIZED
NO DDL AUTHORIZED
NO CODE CHANGES AUTHORIZED
NO MIGRATIONS AUTHORIZED

STOP
```

---

## Mutation Check

```text
PHP / MIGRATION / DDL / RLS / CODE / TESTS MODIFIED: NONE
Deliverable only:
.cursor/database/CORE-DOMAIN-COMPLETENESS-AUDIT.md
```
