# ADR-024: Question Bank, Rubrics and Blueprint-Built Exams

## Status
**Proposed — 2026-10-24. Not approved. No code, migration or UI exists for this ADR.** Implementation starts only after
the human owner approves it (see "Decisions needed"). Part of the EXAMIS program (`docs/sis/exams/EXAMIS-TARGET-DESIGN.md`,
phase D).

## Context
SIS has exams, sessions, seats and grades (`exams.*`) and results (`results.*`), but no questions: an exam is a header with
sessions and a maximum grade, and marks are typed in by teachers per seat. Reference platforms (Moodle question bank,
Inspera, ExamSoft, Gradescope rubrics, TAO/QTI for interchange) treat the question as a first-class, versioned,
reviewable asset. Vendor behaviour in this ADR is a design reference only; **no vendor documentation was re-verified for it**
(traceability item R1 is still open), and nothing is copied from any product.

Constraints that bind this design (they are not options):
- `exams.student_grades` stays the only official grade source. A question bank may produce *marks*, never an official grade
  by itself; marks reach the grade table only through the existing entry/correction commands.
- A question already used in an exam attempt or paper must never change meaning silently → immutable versions.
- Tenant isolation: `school_id` + RLS + FORCE RLS on every new table; every academic row scoped to `academic_year_id` where
  it describes an exam; no hard deletes of anything that was used.
- Domain stays framework-free; handlers own transactions; controllers thin; React holds no business rules.
- PostgreSQL is the source of truth; files are stored outside the database.

## Decision (proposed)

### 1. Entities (new schema `bank`)
| Table | Purpose | Key rules |
|---|---|---|
| `bank.questions` | Stable identity of a question | `school_id`, `subject_id`, `owner_user_id`, `current_version_id`, `status` (`draft`, `in_review`, `approved`, `retired`); never deleted, only `retired` |
| `bank.question_versions` | Immutable content | `question_id`, `version_no`, `type`, `stem`, `payload` (JSONB, validated per type), `max_mark`, `rubric_version_id` (nullable), `created_by`, `created_at`; **INSERT only** (guard trigger as in `exams.student_grades`) |
| `bank.categories` | Topic / unit / lesson tree | self-reference, per subject and school; a question has one primary category and many tags |
| `bank.question_tags` | Free tags / skills / difficulty label | many-to-many, no meaning for authorization |
| `bank.rubrics`, `bank.rubric_versions`, `bank.rubric_criteria` | Marking criteria with levels and marks | versioned like questions; a rubric version is immutable once referenced |
| `bank.assets` | Images, tables, attachments | metadata only (`storage_key`, `sha256`, `mime`, `size`); bytes on the private disk; served through a signed, short-lived, authorized URL |
| `bank.blueprints`, `bank.blueprint_rules` | Exam specification | "N marks from category C at difficulty D"; used to *select* and to *check coverage*, never to grade |
| `exams.exam_forms` | A concrete paper of an exam | `exam_id`, `form_code`, locked at publish; fixed ordered list of `(question_version_id, mark, position)` |
| `exams.exam_form_items` | The items of a form | FK to `question_version_id` (not to the question), so the form can never drift |

Question types in the first release: single choice, multiple choice, true/false, short text (exact/normalized match list),
numeric (value + tolerance), long answer (manual, rubric), multi-part (children with their own marks). Practical/oral
assessments reuse *rubric* + manual marking, with no media capture in this ADR. Matching, ordering, file-upload, code and
equation-editor types are **deferred**.

### 2. Lifecycle and separation of duties
`draft → in_review → approved → (retired)`. Approval requires `bank.question.approve`, and the approver is neither the author
nor the last editor (same four-eyes rule as grade reviews). Editing an `approved` question creates a new `draft` version;
the old approved version stays usable in forms that already include it. Only `approved` versions can be put in a form.
A form is locked when its exam leaves draft; a locked form is replaced by a new form code, never edited.

### 3. Selecting questions
Manual pick, or blueprint-driven selection (a seeded, recorded random draw: seed, rules and result stored on the form so the
selection is reproducible and auditable). Mark totals are validated server-side: sum of item marks = exam maximum grade.
Option shuffling is a per-item flag stored on the form; the shuffle itself belongs to delivery (ADR-025).

### 4. Answer keys and privacy
Keys, accepted answers and rubrics live in `payload`/`rubrics` and are **never** selected by student-facing queries: separate
read model for "student view" (stem + options only) and "marker view". Authorization is by permission, not by hiding fields in
React. Audit every read of a key outside the authoring screens. Item statistics (difficulty, discrimination, unanswered
rate) are computed later from delivery data (ADR-025) into `bank.item_statistics`, derived and rebuildable.

### 5. Interchange
Start with an internal, versioned JSON export/import (round-trip tested). **QTI 3.0 export/import is deferred** to a separate
ADR after the internal model is stable, because the type set above is a subset of QTI and the licence/conformance work is
non-trivial. No third-party question-bank or editor library is adopted by this ADR.

### 6. Permissions (new)
`bank.question.view`, `bank.question.author`, `bank.question.approve`, `bank.rubric.manage`, `bank.blueprint.manage`,
`bank.asset.upload`, `exam.form.manage`, `exam.form.lock`. Teachers see and author questions of subjects they teach
(existing `teaching_assignments` scoping), managers see the school. None implies `grades.*`.

### 7. UI
New pages on the existing chrome (ribbon, `AttendanceTable`, `AttendanceWindow`, RTL): question list/editor, rubric editor,
blueprint editor, form builder with a separate student preview and marker preview. No new styling, no new framework. Rich
text is restricted (sanitized subset); math is rendered from a stored plain notation (MathML/LaTeX choice: open question).

## Alternatives considered
1. **Questions as JSON on the exam row** — rejected: no reuse, no review, no versioning, no statistics.
2. **Mutable questions with an edit log** — rejected: an edit would change what an earlier paper meant; versions are the
   only safe model.
3. **Adopt an external question-bank service / LMS** — rejected for now: identity, tenancy and RLS would be duplicated and
   the result chain (`student_grades`) would depend on a third party. May return as an *import* source via QTI later.
4. **Put the bank in the `exams` schema** — rejected: different lifecycle and ownership; a separate schema keeps RLS,
   permissions and fitness rules clear.

## Consequences
- Large additive migration set (about 10 tables) → follows `database-change` skill, blueprint update, RLS tests for each
  table, partitioning not needed (small volume) but `exam_form_items` indexed by form.
- Authors need review capacity: a school with one teacher cannot approve its own questions → an explicit, audited
  "self-approval allowed" school setting is an open question, default off.
- Storage: private disk and signed URLs become a dependency; backup/restore of assets must be added to the runbook.
- Item statistics are only meaningful after ADR-025 delivers attempt data.

## Decisions needed from the owner
1. Approve the immutable-version model and the four-eyes approval (or state the exception for one-teacher schools).
2. Question types in release 1 (the list above) and which are mandatory for the first school.
3. Rich text and math format; whether images are required in release 1.
4. Who may author: any subject teacher, or only heads of department?
5. Is the internal JSON interchange enough for release 1 (QTI deferred)?
6. Retention: how long are retired questions and unused drafts kept?

## Out of scope
Online delivery and autosave (ADR-025), marking workflows and double marking (ADR-025/B), proctoring, adaptive testing,
AI question generation.
