# SIS Exam Platform — Requirements Traceability (Phase 0 baseline)

**Date:** 2026-10-24 · **Code baseline:** `bcb889a` (branch V2) · **Status:** Phase 0 baseline; updated per unit.
Complements [EXAMIS-TARGET-DESIGN.md](EXAMIS-TARGET-DESIGN.md) (principles, roadmap A–E). No duplicate
documents are created: the roadmap lives there, this file is the evidence matrix.

Status legend: `VERIFIED` (code + tests seen) · `PARTIAL` · `MISSING` · `BLOCKED` · `NOT VERIFIED` · `NOT APPLICABLE`.

## Evidence limits

- **Vendor research is NOT VERIFIED in this session.** No vendor documentation was browsed here. The vendor
  list (PowerSchool, Inspera, ExamSoft, Gradescope, Moodle, Canvas, Blackboard, TAO) was used as reference
  only, through the user's design report of 2026-10-23, which the roadmap condensed. Before relying on any
  vendor-specific claim, Phase 0 research must cite official docs per platform (open item R1).
- Rows below were checked against the repository (migrations, folders, tests listing), not against a live DB.
  The live `sis` DB has not been migrated for U5/U10 and holds no exam data (per project notes).

## Matrix

| # | Requirement (prompt §) | Reference pattern | SIS state | Evidence | Gap / next | Priority |
|---|---|---|---|---|---|---|
| 1 | Exam types, weights per school (6.1) | PowerSchool categories | PARTIAL | migration `2026_10_23_100000_scope_exams_exam_types_to_school`; `/exams/types` | no year/version on weights; no practical-minimum / absence / second-round rules | C |
| 2 | Versioned policy, no silent recompute (6.1, 4.2) | PowerSchool | MISSING | `results.*` rows carry `policy_pin`, no policy table | policy versions per school+year | C |
| 3 | Question bank + versions (6.2) | Moodle, Inspera | MISSING | no question tables in migrations | needs ADR first | D |
| 4 | Rubrics, double / anonymous marking (6.6) | Gradescope | MISSING | — | after bank | D |
| 5 | Exam builder from bank, forms, preview (6.3) | Moodle, Inspera | MISSING | exam admin UI exists (U8, U15) but no question selection | after bank | D |
| 6 | Eligibility, sessions, seating (6.4) | ExamSoft rosters | VERIFIED | U6 `SeatClassInExamSessionHandler`, `tests/Feature/Exams/*`, `e2e/exams` | preview of excluded students before seating | A-adj |
| 7 | Online attempt, autosave, server timer, submit (6.5) | Inspera, Canvas | MISSING | no attempt tables | ADR + security review | E |
| 8 | Official grade source, VOID+INSERT (4.2, 6.7) | — | VERIFIED | `exams.student_grades`, U5 trigger migration, `CorrectVoidFinalizeGradeApiTest` | none | — |
| 9 | Teacher-scoped grade entry (6.6) | Gradescope | VERIFIED | U2 `GradePolicy::enterSeat` | boundary cases documented as DENY | — |
| 10 | Term / annual / GPA / rank / transcript (6.7) | PowerSchool | VERIFIED | U12–U14; `TermResultSheetWebPostgreSqlTest` | second-round & decision marks absent | C |
| 11 | Exact rounding (8.4) | — | VERIFIED | U1 `ResultRounding` | stored results not recomputed (needs authorization) | — |
| 12 | Calculate ≠ Finalize ≠ Publish (6.8) | PowerSchool | PARTIAL (A1 done for annual / GPA of a class or section; term results not yet) | migration `2026_10_24_100000_create_results_result_publications`, `PublishYearResultsHandler`, `WithdrawResultPublicationHandler`, tests `ResultPublicationWebPostgreSqlTest` (6) | term publication; student-facing read = A3 | A |
| 13 | Reconciliation / pass-fail flip report (6.7) | — | PARTIAL | `dataset_incomplete`, `already_official` errors only | **A2** | A |
| 14 | Student / parent view of published results (6.10) | portals | MISSING | Portal context has scope links only | **A3** (after A1) | A |
| 15 | Grade review / appeals (6.9) | Blackboard grade review | MISSING | — | Phase B; deadlines and committees are open academic decisions | B |
| 16 | Notifications on publish / appeal (6.11) | — | MISSING | Communication context exists | test recipients only; after A1 | deferred |
| 17 | Item analysis (6.2, 6.6) | Moodle stats | MISSING | needs attempts | after E | E |
| 18 | QTI import / export (6.2) | TAO | NOT APPLICABLE for now | — | design constraint only; revisit after D | deferred |
| 19 | Proctoring / secure browser (6.5) | ExamSoft | BLOCKED | privacy / legal / cost not assessed | decision required | deferred |
| 20 | Exam status workflow draft→…→archived (6.3) | — | PARTIAL | `ExamStatus` has 5 states | extended states with Phase D/E | D |

## Conflicts with the prompt (to be resolved, not assumed)

1. The prompt asks for a standalone `results` publications log "after documenting the data model". The
   A1 design in EXAMIS-TARGET-DESIGN.md §4 is that documentation; it is ready for implementation.
2. The prompt lists 9 deliverable documents. Existing governance docs (`FEATURE-CONTRACT`, `SECURITY-CONTRACT`,
   `SECURITY-TEST-MATRIX`, `EXAMIS-TARGET-DESIGN`) already cover target architecture, security and roadmap;
   remaining documents will be created only when they carry new content (database impact with A1's migration,
   test evidence after each unit), not as copies.
3. Test baseline: known pre-existing failures are listed in project notes (exam.view wildcard test, Phase3A/3B
   "no 3C tables", `ExampleTest`, 3 `StudentUiTest`, `composer audit`-driven validator test). They must be
   re-measured on a fresh PostgreSQL DB before A1 to separate them from new failures (open item T1).

## Open items

- **R1** — Cite official vendor documentation per platform (currently NOT VERIFIED).
- **T1** — Re-run the baseline suite on a disposable PostgreSQL DB; record counts.
- **D1** — Academic decisions needed before B/C: appeal window, committee composition, second-round rules,
  decision-mark rules, practical minimum.
- **D2** — ADRs needed before D (question bank) and E (online delivery).

## A1 evidence (2026-10-24)

- Table `results.result_publications`: FKs to school / year / class / section / users, CHECKs (kind, status, withdrawn
  reason), partial unique index (one active publication per school-year-class-section-kind), `ENABLE ALWAYS` guard trigger
  (no delete, immutable snapshot, withdrawn is final), RLS + FORCE RLS. Additive; `down()` drops the table and function.
- Permission `results.publish` and new role `results_publisher` (results.view + results.publish). Deviation from the
  design note: the role is separate instead of `grades_manager`, because `grades_manager` has no `results.view`.
  `results_manager` (calculate / finalize) does NOT publish: tested.
- Command: `phpunit -c phpunit.database-pgsql.xml --filter "ResultPublication|YearResultSheet|TermResultSheet|Phase76Results|Results|ExamisDemoData|Permission|SecurityPermission"` on throwaway DB `sis_test_pub` → 93 tests, 93 passed, 745 assertions.
- `architecture:feature-check Results` passes. `architecture:validate --fitness` still fails only on pre-existing items
  (Admission / Student / Teachers handlers, Enrollment / Curriculum controllers, composer audit).
- `npm run types:check`: no errors in `year-sheet.tsx` or `ar.ts`. No browser check done yet (NOT VERIFIED).
- Live `sis`: only this migration was pending (U5 and U10 were already applied); applied with `migrate --force`, then
  `SecurityPermissionSeeder` (upsert). Verified: RLS + FORCE true, trigger enabled (`A`), permission and role present.
- Not done: student-facing visibility of published results (A3), notifications, term-result publication.

## A3 + A2-lite evidence (2026-10-24)

- **A3 (row 14, now PARTIAL):** the portal API `GET /api/v1/portal/results/annual` and `/gpa` return 404
  (`results.annual_not_found` / `results.gpa_not_found`, same as "no result", so existence is not leaked) unless an
  active publication snapshots the EXACT current official version (`ResultPublicationRepositoryInterface::isPublished`,
  JSONB containment). A rebuild (new official version) is therefore hidden until withdrawn and published again.
  Term results and transcripts have no publication step yet and stay visible as before (GAP, documented in the
  controller docblock). This changes a Phase 7.8 contract on purpose: the Phase78 tests now seed a publication.
  No student-facing web page exists yet; the portal is still API-only.
- **A2-lite (row 13, PARTIAL):** the year sheet lists each active publication with `drifted` = published entries whose
  official result changed since; the UI warns and asks to withdraw and publish again. The full reconciliation report
  (seated vs resulted, pass/fail flips before finalize) is still open.
- Tests: `--filter "ResultPublication|YearResultSheet|Phase78|Phase79|Phase76Results|TermResultSheet|ExamisDemoData"` on a
  throwaway DB → 66 tests, 66 passed, 466 assertions. New: 2 portal tests, 1 drift test. No new migration.
- Bug caught by the drift test before shipping: a `static fn` closure used `$this` (500 on the sheet).

## Student web page + B (grade reviews) evidence (2026-10-24)

- **Row 14 student view → PARTIAL (web page done):** `GET /portal/results` («نتائجي», `PortalResultsPageController`,
  `EloquentPortalPublishedResultsReadRepository`): rows only for students the user owns (own scope or verified guardian,
  `PortalPartyAccessService::allowedStudentIds`) AND only where an active publication snapshots the current official
  version. Unpublished data is never sent to the page. Tests: 2 new in `Phase78PortalResultsHttpApiPostgreSqlTest`.
  No sidebar link was added (the sidebar is a global static list; changing it needs a decision). Term results and
  transcripts have no publication step yet.
- **Row 15 appeals → PARTIAL (grade-level review done):** migration `2026_10_24_110000_create_exams_grade_reviews`
  (`exams.grade_reviews`: composite FKs to the partitioned `student_grades`, status CHECKs, one open review per grade,
  guard trigger `ENABLE ALWAYS` — no delete, decided is final, request immutable, RLS + FORCE). Workflow: open →
  take (`under_review`, assigned) → accept (calls the official `CorrectStudentGradeHandler`: VOID + INSERT, review number in
  the reason, `resulting_grade_id` recorded) or reject, or the requester withdraws. Four-eyes rule: the reviewer is neither the
  requester nor the enterer of the current grade; only the assignee decides. Permissions `grades.review.request` /
  `grades.review.decide` (grades_manager). Page `/grades/reviews`; «فتح مراجعة» on the grade actions page.
  Tests: `GradeReviewWebPostgreSqlTest` (9).
- **Not assumed (decisions D1):** no appeal deadline is enforced, no committee, and students cannot file reviews yet
  (they cannot see individual grades). Accepting a review does not recalculate results: use the results rebuild path; the
  publication drift warning shows what changed.
- Command: `phpunit -c phpunit.database-pgsql.xml --filter "Grade|Permission|Exam|Result|Portal|Security"` on a throwaway DB
  → 258 tests, 258 passed. `architecture:feature-check Exams` and `Results` pass. `npm run types:check`: no errors in the
  touched files. Not run: browser check of the new pages (NOT VERIFIED), `tests/Feature/Exams` (needs a fresh DB).
- Live `sis`: migration applied with `migrate --force` + `SecurityPermissionSeeder`; RLS + FORCE true, 2 permissions present.

## A1b — term and transcript publication (2026-10-24)

- Migration `2026_10_24_120000_extend_result_publications_for_term_and_transcript` (additive ALTER): `term_id`, `subject_id`
  (FKs), kind CHECK `annual|gpa|term|transcript`, term-scope CHECK, scope-aware active-unique index, guard freezes the new
  columns. `down()` refuses to run while term/transcript rows exist (publications are never deleted).
- `PublicationSnapshotBuilder` builds the all-or-nothing snapshot per kind. Term publication is per term + subject of a
  class/section (button on «احتساب نتائج الفصل»); transcripts publish from «النتيجة السنوية والمعدل» and need an
  up-to-date issued transcript for every student.
- The portal API (`term`, `annual`, `gpa`, `transcripts/issued`) and the page `/portal/results` now show every official
  result only while a publication snapshots its exact version; the earlier GAP (term/transcript visible unpublished) is closed.
  Shared UI: `components/results/publication-list.tsx` (list, drift warning, withdraw with reason).
- Tests: `--filter "Grade|Permission|Exam|Result|Portal|Security"` on a throwaway DB → 260 tests, 260 passed.
  New: term publish + drift, transcript publish; Phase78 portal tests updated (all four kinds published/withdrawn).
- Live `sis`: migration applied (`results.result_publications` had 0 rows). Browser check of the sheets: NOT VERIFIED.

## A2 — reconciliation before finalize / publish (2026-10-24)

- Row 13 → PARTIAL+: `Domain/Results/Services/ResultsReconciliation` (+ `IssueBag`) classifies the rows each sheet already
  shows, so numbers always agree with the table. Term sheet: not graded, grades not finalized, no result, calculated but not
  finalized, official incomplete, calculated differs from official; `ready.term`. Year sheet: no official term results,
  annual / GPA missing, incomplete or differing, transcript missing or outdated, ranking missing; `ready.annual|gpa|transcript`.
  Shown by `components/results/reconciliation-panel.tsx` on both sheets. Read-only: nothing is recalculated.
- NOT done: pass/fail **flip** detection (the sheet read model carries values, not pass/fail; the old-vs-new comparison needs
  the rebuild preview and a decision on what "flip" compares against), and a seated-students-vs-roster check beyond the
  sheet's own scope.
- Tests: `ResultsReconciliationTest` (5 unit), 1 feature test (both sheets carry the report); filter
  `ResultPublication|YearResultSheet|TermResultSheet|Reconciliation|Phase78` → 41 passed. Types clean; fitness shows no Results violation.

## ADRs written for review (2026-10-24) — no code
`.cursor/architecture/adr/ADR-024-question-bank-and-rubrics.md` and `ADR-025-online-exam-delivery.md`, status **Proposed**.
Rows 3, 4, 5, 7, 17 stay MISSING/BLOCKED until the owner answers the "Decisions needed" lists in the ADRs.
