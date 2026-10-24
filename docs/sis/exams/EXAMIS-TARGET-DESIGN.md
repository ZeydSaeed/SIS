# EXAMIS — Target Design, Gap Analysis and Roadmap

**Status:** target design (direction, not a contract). Binding rules stay in
[FEATURE-CONTRACT.md](FEATURE-CONTRACT.md) and [SECURITY-CONTRACT.md](SECURITY-CONTRACT.md).
**Source:** the EXAMIS design report «التقرير الشامل: تصميم منظومة EXAMIS للاختبارات والدرجات والنتائج»
(2026-10-23), condensed. Vendor references (PowerSchool, Inspera, ExamSoft, Gradescope, Moodle, Canvas,
Blackboard, Questionmark, TAO, Iraqi EMS) were studied for principles only. EXAMIS copies none of them.
**Contexts:** `Exams` (exam → session → seat → `exams.student_grades`) and `Results` (term / annual /
GPA / ranking / transcript).

## 1. Binding principles

1. **SIS owns identity.** Students, enrollments, years, terms, schools, classes, sections, subjects and
   teaching assignments are referenced by id and never re-created inside EXAMIS.
2. **One official grade source.** `exams.student_grades` is the only official grade. Corrections are
   VOID + INSERT linked to the previous row, and an in-place UPDATE is blocked by a trigger. Online
   delivery, rubrics or appeals must feed this table, never a parallel "official" store.
3. **Every grade has a source; every correction has a trace; every official result has an approval
   path and a version.**
4. **Calculate → Review → Finalize → Publish are four separate operations** with separate permissions.
   A calculated or finalized result is not visible to students until it is published.
5. **Versioned policy.** Weights, pass thresholds, rounding, absence and second-round rules belong to
   a dated, versioned policy, not to code. A policy change creates a new version and never silently
   recomputes stored results. Recomputation needs explicit authorization.
6. **Reproducible results.** Each result records the inputs it used (`source_fingerprint`), the policy
   (`policy_pin`), its version, and who calculated and finalized it.
7. **Rounding never decides pass/fail by accident.** Rounding uses exact half-up bcmath
   (`ResultRounding`). Regression tests guard the 49.995 boundary.
8. **Server-side state machines.** Every status transition (exam, session, seat, grade, result,
   publication) is checked on the server against its preconditions and permission.
9. **Separation of duties.** Entering, reviewing, approving and publishing are distinct permissions.
   A technical admin role does not imply authority over academic grades. One person may hold several
   roles in a small school, but high-impact actions can require a second approver.
10. **Seat ≠ attempt.** A seat (eligibility in a session) exists before, and independently of, any
    answering attempt.
11. **Privacy.** Notifications go to test recipients only outside production. Practical-assessment
    evidence (photos or video) needs a purpose, access control and a retention rule.

## 2. Lifecycle (target)

```text
Policy (versioned) → Blueprint → Question bank (versioned) → Exam + sessions
→ Eligibility + seating → Delivery (paper | online | practical | mixed) → Marking (auto | manual | rubric)
→ Grade review / appeals → Result calculation → Reconciliation + finalize → Publish → Portal / transcript
```

Target exam states: draft → under review → approved → scheduled → published → running → closed →
marking → approval → results published → archived (+ cancelled / postponed / suspended).
Today the code implements Draft / Scheduled / InProgress / Completed / Cancelled (`ExamStatus`).

## 3. Gap analysis (code as of bcb889a)

| Area | Status | Where |
|---|---|---|
| SIS-owned identity, RLS, academic-year scoping | ✅ | Exams / Results contexts |
| Exam + sessions, guarded transitions, edit/cancel | ✅ | `Domain/Exams/Support/*`, U8, U15 |
| Bulk seating, server-side eligibility | ✅ | U6 `SeatClassInExamSessionHandler` |
| Preview of excluded students and reasons before seating | ❌ | — |
| Official grade source, VOID+INSERT, immutable rows | ✅ | U5 trigger |
| Teacher-scoped grade entry | ✅ | U2 `GradePolicy::enterSeat` |
| Grade "review required" state / reviewer role | ❌ | `GradeStatus` has Draft/Entered/Submitted/Finalized/Voided |
| Exact rounding | ✅ | U1 `ResultRounding` |
| Calculate / finalize term, annual, GPA; ranking; transcript | ✅ | U12–U14 |
| **Publish separate from finalize** | ❌ → Phase A1 | — |
| Reconciliation report before finalize (seated vs. resulted, pass/fail flips) | ⚠️ partial | `dataset_incomplete`, `already_official` |
| Student/parent view of published results | ❌ | Portal has scope links only |
| Versioned policy per school + year | ⚠️ | U10 per-school exam types; weights frozen once used; no year/version |
| Independent pass rules (practical minimum, absence, second round, decision marks) | ❌ | — |
| Grade review / appeals | ❌ | — |
| Question bank, versions, blueprint coverage | ❌ | — |
| Rubrics (versioned), double marking, anonymous marking | ❌ | — |
| Online delivery: attempt, autosave, server timer, submit, auto-marking | ❌ | — |
| Item analysis (difficulty, discrimination, unanswered) | ❌ | depends on question bank |
| Publication notifications | ❌ | Communication context exists |
| QTI import/export | ❌ | design constraint only |

## 4. Roadmap

The roadmap follows the same program as U1–U15: one small unit at a time, verified and reported.

| Phase | Units | Notes |
|---|---|---|
| **A — results governance** | A1 publication record + publish/withdraw (`results.publish`) · A2 reconciliation report on the term/year sheets · A3 portal view of published results only | no new subsystem; highest value |
| **B — grade review & appeals** | `GradeReview` (number, reason, status, decider); acceptance goes through the existing correction | no direct edit of official grades |
| **C — policy versioning** | versioned grading policy per school + year; results pin the version | no silent recompute |
| **D — question bank + rubrics** | versioned questions, review lifecycle, rubric versions, blueprint coverage | needs an ADR first |
| **E — online delivery** | attempts, autosave, server-side timer, submit, auto-marking, then item analysis | ADR + security review |
| Deferred | QTI, proctoring / secure browser, notifications beyond test recipients | — |

### A1 design (result publication)

- Table `results.result_publications`: one row per published scope (school, year, kind
  term|annual|gpa, term, class, optional section) with status `published | withdrawn`, who and when,
  and a withdraw reason. Result rows themselves are never mutated by publication.
- Only one active publication per scope (partial unique index). Rows are never deleted: withdrawal
  is a status change with a reason. RLS by `school_id`.
- Publishing is refused unless every student in scope has a current **official** result.
- Permission `results.publish` is granted to `grades_manager` only. Finalize does not imply publish.

## 5. Status update (2026-10-24)

Done and recorded in [SIS-EXAM-REQUIREMENTS-TRACEABILITY.md](SIS-EXAM-REQUIREMENTS-TRACEABILITY.md): A1 (publication of
annual / GPA / term / transcripts), A2 (reconciliation panel), A3 (student page + gated portal API), B (grade reviews).
Open: C (policy versioning), D and E. D and E have proposed ADRs awaiting approval:
[ADR-024](../../../.cursor/architecture/adr/ADR-024-question-bank-and-rubrics.md),
[ADR-025](../../../.cursor/architecture/adr/ADR-025-online-exam-delivery.md).
