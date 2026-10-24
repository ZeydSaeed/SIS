# SIS Exam Platform — Readiness Report (interim, 2026-10-24)

**Verdict: NOT operationally ready as a complete exam platform.** The results/grades/publication chain is built, tested and
checked in a browser on a throwaway database. The question bank and the online exam are NOT built (design only, awaiting
approval). Branch V2. Committed by the owner in `faea262` and before: units U1–U15, publication (A1/A1b), reconciliation (A2), portal (A3), grade reviews (B), ADR-024/025. NOT committed: per-year weight versions (C, migration `2026_10_24_130000`), this report and the operations documents. Operational task list: [SIS-EXAM-OPERATIONS-TASKS.md](SIS-EXAM-OPERATIONS-TASKS.md); notes: [SIS-EXAM-OPERATIONS.md](SIS-EXAM-OPERATIONS.md).
Evidence matrix: [SIS-EXAM-REQUIREMENTS-TRACEABILITY.md](SIS-EXAM-REQUIREMENTS-TRACEABILITY.md). Roadmap:
[EXAMIS-TARGET-DESIGN.md](EXAMIS-TARGET-DESIGN.md). Written for: the SIS owner deciding what to approve next.

## 1. What exists and was verified

| Capability | State | Evidence |
|---|---|---|
| Exam → session → seat → official grade (`exams.student_grades`, VOID + INSERT, in-place UPDATE blocked) | VERIFIED | units U1–U15, `tests/Feature/Exams`, trigger migration |
| Teacher-scoped grade entry, grade sheet | VERIFIED | U2/U3 |
| Term / annual / GPA results, ranking, transcripts, exact half-up rounding | VERIFIED | U1, U12–U14 |
| Per-school exam types; **per-year weight versions**, pinned in `policy_pin`, no silent recompute | VERIFIED (C) | `ExamTypeWeightVersionWebPostgreSqlTest` (6), browser check of «أنواع الامتحانات» |
| Calculate ≠ finalize ≠ **publish**; publication of annual / GPA / term / transcripts with a fingerprinted snapshot, one active per scope, withdrawal with reason, drift warning | VERIFIED (A1, A1b) | `ResultPublicationWebPostgreSqlTest`, browser: published and listed |
| Reconciliation panel before finalize/publish | VERIFIED (A2), no pass/fail-flip detection | `ResultsReconciliationTest` (5 unit) + feature test + browser |
| Student/guardian page «نتائجي» and portal API show only published, current-version results | VERIFIED (A3) | Phase78 tests, browser (student login), phone 390 px and tablet 820 px: no page-level horizontal scroll, RTL |
| Grade reviews (objections): open → take → accept (official correction) / reject / withdraw, four-eyes rule | VERIFIED (B) | `GradeReviewWebPostgreSqlTest` (9), browser (take + reject by a second user) |

## 2. What does not exist

| Capability | State | Why |
|---|---|---|
| Question bank, rubrics, exam forms | MISSING / BLOCKED | ADR-024 proposed, needs owner approval; no code |
| Online attempts, autosave, server timer, auto-marking | MISSING / BLOCKED | ADR-025 proposed + security review; no code |
| Student-filed objections, appeal deadline, committee | MISSING / BLOCKED | academic decisions D1 not given |
| Policy: effective dates, second approver, pass thresholds, absence, second round, decision marks | MISSING / BLOCKED | academic decisions D1 |
| Pass/fail flip detection in reconciliation | MISSING | needs rebuild preview + a decision on the comparison |
| Notifications, reports/analytics, QTI, proctoring | MISSING / DEFERRED | after D/E; proctoring needs its own ADR |
| Vendor research with official sources | NOT VERIFIED | not done in any session (R1) |

## 3. Tests (commands and results actually run)

- Targeted, throwaway PostgreSQL DBs, `php -d memory_limit=2G vendor/bin/phpunit -c phpunit.database-pgsql.xml --filter …`:
  last runs: `Grade|Permission|Exam|Result|Portal|Security|Phase7` → **282 tests, 282 passed**; `tests/Unit/Results` → 29 passed;
  per-unit runs listed in the traceability file.
- Full PostgreSQL suite (T1): see §6.
- `npm run types:check`: no errors in the files touched by this work. `npm run build` ran clean.
- `php artisan architecture:feature-check Exams|Results`: pass. `architecture:validate --fitness`: fails only on items older than
  this work (Admission / Student / Teachers handler complexity, Enrollment / Curriculum controllers, `composer audit`).
- Browser (headless Chrome driven by Playwright against `php -S` on a throwaway DB, three users): 15 checks, 15 passed on the
  final run (year sheet panel and publication, distinct accessible names of the publish commands, term sheet, exam types year
  weight, grade review take + reject, student portal, no page errors; phone and tablet widths). Found and fixed on the way:
  three publish commands shared one accessible name; the types page needed `npm run build` to show the new column.
- Not run: Playwright suite under `e2e/` for the new pages (no specs written), load/concurrency tests of publish and review under
  parallel requests (only DB constraints and sequential idempotency are tested), accessibility audit with a screen reader.

## 4. Database (live `sis`, applied with explicit approval, additive only)

`results.result_publications` (+ term/transcript columns), `exams.grade_reviews`, `exams.exam_type_weight_versions`, plus the
earlier exam-type and trigger migrations. Each has FKs, CHECKs, guard triggers (`ENABLE ALWAYS`: no delete; publications and
reviews freeze once decided; weight versions are insert-only), RLS + FORCE RLS (verified on `sis`). Existing data untouched
(`sis` held no exam data). `down()` of the publication extension refuses while term/transcript rows exist.

## 5. Risks and remaining items

| Level | Item | Next step |
|---|---|---|
| BLOCKER for "full platform" | No question bank / online exam | owner reviews ADR-024/025 and answers their decision lists |
| HIGH | Academic policy decisions D1 (appeal window, committee, effective dates, approvers, thresholds, second round) | owner decides; then C-phase 2 and appeals phase 2 |
| HIGH | Accepting a grade review does not recalculate results | a reviewed "rebuild after review" flow (the drift warning exists) |
| MEDIUM | No pass/fail flip detection; no e2e specs for new pages; no parallel-request tests | add after D1 |
| MEDIUM | Vendor research not verified (R1) | cite official docs per platform |
| LOW | Navigation link for «نتائجي» was added by the owner's commit; confirm it shows only for portal users | check |
| DEFERRED | Notifications, analytics, QTI, proctoring | after D/E |

Separate assessments (no overall percentage is claimed): functional requirements — results chain complete, authoring/delivery
absent; UI — all built pages checked once in a browser, not exhaustively; database — complete for the built scope; security and
result integrity — DB-enforced and tested for the built scope, no external review; test coverage — strong on the new backend,
thin on parallelism and e2e; operations — backup/restore, monitoring and a runbook for these tables are NOT written.

## 6. Full-suite baseline (T1) — COMPLETED 2026-10-24

**Command (run once, fresh PostgreSQL database `sis_test_full`, created empty beforehand):**
`DB_DATABASE=sis_test_full SIS_PGSQL_TEST_DATABASE=sis_test_full SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_test_full php -d memory_limit=3G vendor/bin/phpunit -c phpunit.database-pgsql.xml > full_pg.txt`

**Completion check:** the PHPUnit process was gone and the output file held one complete JSON report (22,343 bytes, parsed whole).
A first watcher that only waited for a non-empty file was stopped as insufficient. Note: the "exit code 0" shown by the task runner
is the exit code of the trailing summary script, **not** of PHPUnit; PHPUnit's own exit status was not captured. Its report says
`"result": "failed"`, which corresponds to a non-zero status.

**Result:** 787 tests · 783 passed · 3 failed · 1 error · 6,199 assertions · 1,051 s. The reporter has no skipped / incomplete / risky /
warning counts, so those are **not reported** (not asserted to be zero). Every test added by this work passed in this run.

| # | Test | Message (short) | Class | Evidence |
|---|---|---|---|---|
| 1 | `PhasePtPromotionSchemaPostgreSqlTest::transfers_tables_remain_absent` | expected 0 transfer tables, found 2 | **OLD** (known) | already recorded as failing on 2026-10-12/18 (project notes); asserts an outdated schema fact; no file of this work touches promotion/transfers; fails again in isolation on a fresh DB |
| 2 | `GraduationWritePathPostgreSqlTest::multi_enrollment_outcomes_are_independent` | unique violation `enrollments_student_year_active_unique` | **OLD** (known) | same notes; fails in isolation; no change of this work in graduation/enrollment code |
| 3 | `LeaveModulePostgreSqlTest::the_pages_render_with_live_data` | `LeaveRuleViolation: duration_zero`, flash null | **UNRELATED, probably date-dependent** | the test files a one-day leave for `now()` (line 236) and the run date is Saturday 2026-10-24, so zero working days; fails in isolation too. The weekend explanation is a hypothesis: it was NOT confirmed by running on a weekday |
| 4 | `LeaveDemoSeederPostgreSqlTest::the_seeded_data_is_consistent_for_every_status_and_path` | "first school of the empty test database": got 53, expected 372 (1 vs 11 in isolation) | **UNRELATED, not previously recorded** | assertion on database/sequence state; fails in isolation too; Leave module is not touched by this work |

**Limits of the classification:** a baseline of the same suite at an earlier commit was NOT run (it needs a second checkout or git
operations, which this task forbade). "OLD" for 1–2 rests on the project notes plus unchanged code; 3–4 are "unrelated to this work"
because no file in those areas is modified and they fail in isolation, but they are **not proven pre-existing** and are not recorded
in the earlier notes. They need an owner decision (fix the tests, or confirm on a weekday / earlier commit).

Isolation re-run (fresh DB `sis_test_iso`, filter on the four classes): 25 tests, 21 passed, the same 4 failed. Both temporary
databases were dropped afterwards.
