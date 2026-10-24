# ADR-025: Online Exam Delivery (Attempts, Autosave, Server Timer, Marking Hand-off)

## Status
**Proposed — 2026-10-24. Not approved. No code, migration or UI exists for this ADR.** Depends on ADR-024 (forms of
approved question versions). Needs an explicit security review before any implementation. Part of the EXAMIS program
(`docs/sis/exams/EXAMIS-TARGET-DESIGN.md`, phase E).

## Context
Today an exam seat (`exams.exam_enrollments`) records that a student *may sit* a session; marks are entered by staff. There is
no way for a student to answer in SIS. Reference platforms (Inspera, Canvas/Moodle quizzes, ExamSoft, TAO) deliver the paper,
keep time on the server, autosave answers and hand the result to marking. Vendor behaviour here is a design reference only
and was **not re-verified** (open item R1).

Binding constraints:
- **Seat ≠ attempt.** The seat exists before and independently of any attempt; delivery adds attempts, it does not change seats.
- **The server is the only authority** for time, eligibility, saved answers and submission. The browser timer is cosmetic.
- An attempt produces *marks*; the official grade is still written only through `EnterStudentGrade` / `CorrectStudentGrade`
  after the normal review/finalize path. An auto-marked score is never an official grade by itself.
- No claim that a browser can prevent or reliably detect cheating. Any monitoring is limited, disclosed and off by default.
- Tenant isolation (`school_id`, RLS + FORCE RLS), academic-year scoping, no hard deletes, PostgreSQL as the source of truth,
  Redis only as cache; heavy work on queues.

## Decision (proposed)

### 1. Entities (schema `delivery`)
| Table | Purpose | Key rules |
|---|---|---|
| `delivery.attempts` | One try of one seat | `exam_enrollment_id`, `exam_form_id`, `attempt_no`, `status` (`in_progress`, `submitted`, `expired_submitted`, `voided`), `started_at`, `deadline_at` (set once by the server), `submitted_at`, `seed`, `client_token_hash`; partial unique index: one `in_progress` attempt per seat |
| `delivery.attempt_items` | The paper as this student saw it | `attempt_id`, `exam_form_item_id`, `position`, `option_order` (JSONB); written at start, immutable |
| `delivery.answers` | Latest saved answer per item | `attempt_id`, `attempt_item_id`, `answer` (JSONB), `revision` (monotonic), `saved_at`; optimistic concurrency by `revision` |
| `delivery.answer_revisions` | Append-only history | every accepted save; basis for conflict resolution and dispute evidence; retention per policy |
| `delivery.attempt_events` | Minimal event log | start, resume, submit, expire, forced-void, server-detected anomalies (e.g. rejected stale revision); **no** keystroke, screen or camera data |
| `delivery.item_marks` | Marks per attempt item | `source` (`auto`, `manual`, `rubric`), `mark`, `marker_id`, `rubric_scores` (JSONB), `status`; versioned by VOID + INSERT like grades |

All tables carry `school_id` and `academic_year_id`; guard triggers forbid deleting attempts/answers and editing a submitted
attempt's answers or items.

### 2. Start / resume / save / submit
- **Start** (`POST`): checks seat active, session open, eligibility (existing rules), no other in-progress attempt, attempts
  limit of the exam policy. Server sets `started_at`, `deadline_at = min(started_at + duration, session window end)`, picks
  the form (fixed or seeded draw from the exam's forms), writes `attempt_items`. Idempotent by key.
- **Resume**: same endpoint returns the existing in-progress attempt and the *server's* remaining time; a second device takes
  over only by an explicit confirm which rotates `client_token_hash` (the old tab then gets 409).
- **Autosave**: `PUT` with `(item, answer, revision)`; accepted only while `now() < deadline_at + grace` (grace is a small, fixed,
  logged server constant, default 0 until the owner decides). Stale `revision` → 409 with the stored answer; the client shows
  "saved / saving / offline". Saves are idempotent, rate-limited per attempt, size-limited.
- **Offline / reconnect**: the client keeps unsent answers in memory/localStorage *only as a convenience*; on reconnect it
  replays them in order. Anything that reaches the server after the deadline is rejected and logged. No time is credited for
  offline periods.
- **Submit**: manual, or automatic at deadline by a scheduled job (and lazily on any later request). One transition
  `in_progress → submitted | expired_submitted` guarded by `SELECT … FOR UPDATE`; repeats are no-ops returning the receipt.
  The receipt (attempt number, server time, answer-set hash) is shown to the student and stored.

### 3. Marking hand-off
On submit, a queued job auto-marks objective items (choice, true/false, short text, numeric) into `delivery.item_marks`
(`source=auto`) and creates manual-marking work for long answers. Markers use existing roles/scoping; a marker may be given
anonymized attempts (the student identity is not in the marker read model). Rubric scoring uses ADR-024 rubric versions.
When all items are marked and reviewed, a *proposal* of the seat's total is offered to the grade-entry sheet; entering it
into `exams.student_grades` stays a separate, permissioned, audited action (and then flows through grade review, finalize,
results, publication). Re-marking after a question error: a new mark version per affected item and a recomputed proposal;
already-entered official grades are corrected only through `CorrectStudentGrade`.

### 4. Security and integrity
- Authorization on every call by seat ownership (student or no one); students never receive keys, rubrics or other students'
  data; the student read model has no mark columns until results are released by policy.
- CSRF and session as today; per-attempt token; throttling; request size caps; no secrets or answers in logs.
- Time: only server clocks; `deadline_at` is stored, never recomputed from client input.
- Concurrency: row lock on the attempt for start/submit; revision check for saves; partial unique index prevents two active
  attempts.
- Browser limits are stated plainly to users: tab-visibility or focus events may be *recorded as events* only if the owner opts
  in per exam; they never auto-fail a student.
- **Proctoring / secure browser / camera / screen capture are not part of this ADR.** They would need a separate ADR with
  privacy, consent, legal, cost and vendor review.

### 5. Performance
Autosave writes are small single-row upserts plus an append; target workload to be *measured* (not assumed) with a load test
of N concurrent attempts before approval for production. `answer_revisions` is range-partitioned by month (default partition
forbidden, per the grades rule); old revisions are archived by policy. Redis may cache the form (read-only), never answers.

### 6. UI
A student exam shell on the existing system chrome, mobile-first and RTL: instructions → start, one-question or paged view
per exam setting, question palette with answered / flagged states, save indicator, server-synced countdown, submit
confirmation, receipt. Staff side: attempt monitor (in progress / submitted / expired / anomalies), void/resume actions with
reason, marking queue. Playwright E2E for desktop, tablet and phone, including offline/reconnect and double-submit.

## Alternatives considered
1. **Browser-side timer and local grading** — rejected: not authoritative, trivially manipulated.
2. **WebSocket-only autosave** — deferred: plain HTTPS with idempotent PUT is simpler to secure and test; revisit if measurements demand it.
3. **External exam engine (TAO, Inspera) behind LTI/QTI** — rejected for now: cost, data residency and a second identity/tenant
   model; the owner may revisit.
4. **Store answers only in the final attempt row** — rejected: no concurrency control, no history for disputes.

## Consequences
- New schema with write-heavy tables → partitioning, retention and backup policy must be written before go-live.
- A scheduler/queue worker becomes critical for auto-submit: monitoring and a documented fallback (lazy submit on next request).
- Exam day operations (support, device failure, extra time, interruption policy) are academic decisions, not engineering ones.
- Accessibility (extra time, screen readers) must be designed in, not added later.

## Decisions needed from the owner
1. Approve the seat/attempt split and "marks are proposals; grades go through the existing path".
2. Deadline grace period (default 0) and the policy for an interrupted attempt (resume time credited? who decides?).
3. Attempts per exam (1 for formal exams?) and who may grant a re-sit; extra time rules per student.
4. Whether any focus/visibility events may be recorded (opt-in per exam) — and the privacy notice that goes with it.
5. Which schools/exams pilot first and the maximum concurrent attempts to support (drives the load test).
6. Retention of answers and revisions.
7. Security review owner and sign-off before implementation starts.

## Out of scope
Question bank and rubrics (ADR-024), proctoring and secure browser, adaptive tests, AI marking, LTI/QTI delivery,
native mobile apps.
