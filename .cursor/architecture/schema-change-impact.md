# Schema Change Impact Analysis

> **Mandatory** for every schema change — complements DATABASE-CHANGE-CHECKLIST.md  
> **Adaptive rule:** analyze impact, do not apply static index/partition rules blindly.

---

## Change Classification

| Class | Examples | Risk |
|-------|----------|------|
| **Low** | Add nullable column, new reference table | Low |
| **Medium** | New FK, new index, new RLS policy | Medium |
| **High** | Partition change, column type change, table split | High |
| **Critical** | DROP table/column, remove FK, change PK | Critical |

---

## Universal Checklist (All Changes)

- [ ] Updated `database-blueprint.md`
- [ ] Updated `database-dictionary.md` (if new table/column)
- [ ] Migration up + rollback tested
- [ ] PHPStan / tests pass
- [ ] DATABASE-CHANGE-CHECKLIST completed

---

## Add New Table

### Step 1 — Logical Design

- [ ] Entity fits domain schema (organization, students, etc.)
- [ ] 3NF verified — no repeating groups
- [ ] Relationship cardinality documented in erd-overview.md

### Step 2 — Referential Integrity

- [ ] FK defined with explicit ON DELETE (restrict for academic)
- [ ] Nullable vs NOT NULL justified
- [ ] CHECK constraints for bounded values

### Step 3 — Performance (Measured Need, Not Automatic)

| Question | Action if YES |
|----------|---------------|
| FK column used in JOIN/WHERE? | Consider B-Tree index |
| Table expected > 10M rows/year? | Partition review |
| High write rate (> 100K/day)? | Batch write pattern |
| Dashboard aggregation? | Consider summary table or MV |

**Do NOT** auto-index every FK on a 10K-row table.

### Step 4 — Security

- [ ] RLS needed? (multi-school scope)
- [ ] PII columns identified in dictionary
- [ ] Audit logging for CRUD

### Step 5 — Application

- [ ] Model namespace: `App\Models\{Domain}\{Model}`
- [ ] Policy class
- [ ] Service (if business logic)
- [ ] API/Inertia page if user-facing

### Step 6 — Cache & Reports

- [ ] New cache keys in cache-invalidation.md?
- [ ] Existing MVs still valid?
- [ ] New MV needed? (only if query > 2s proven)

---

## Add New Relationship (FK)

Example: `students → student_transfers → schools`

```text
Relationship Impact Analysis
├── Referential Integrity
│     FK, ON DELETE, ON UPDATE, NULLABILITY
├── Performance
│     JOIN patterns, index on FK columns, cardinality estimate
├── Security
│     RLS: school_id scope on transfer table
├── Application
│     Model relationships, API, DTOs, Queries
├── Reports
│     MV refresh impact, new aggregates?
├── Cache
│     Invalidation keys for school/student dashboards
└── Audit
      Log create/update on transfer records
```

---

## Add Column

- [ ] Default value or backfill plan for existing rows
- [ ] Zero-downtime path if NOT NULL (expand → backfill → contract)
- [ ] Index needed? (only if WHERE/JOIN on column — prove with query)
- [ ] App + API + dictionary updated

---

## Remove Column / Drop Table

### Hard Stop — Critical Path

```text
1. Dependency discovery
   ├── FK referencing this table/column
   ├── Views / Materialized Views
   ├── Application grep (models, services, API, jobs)
   ├── Reports and exports
   ├── Cache keys
   └── External integrations

2. Deprecation
   ├── Rename to _deprecated_* (one release)
   ├── Monitor zero usage
   └── Announce to team

3. Backup
   └── Snapshot before DROP

4. Approval
   └── Tech lead + DBA for academic tables

5. DROP
   └── Migration with rollback (recreate empty structure)
```

**Academic official data:** prefer archive (`status`, `archived_at`) over DROP.

---

## Partition Decision (New High-Growth Table)

Use decision tree from DATABASE-ADAPTIVE-GOVERNANCE.md:

```text
Size + Growth + Retention + Query Pattern + Maintenance Cost → YES/NO/DEFER
```

Document in ADR if YES.

---

## Evidence Required for Performance Claims

Before adding index/partition/MV for this change:

```yaml
query:
  sql_or_description:
  frequency_per_day:
  current_p95_ms:
  explain_before:
proposed_optimization:
  type:
  expected_improvement:
  write_overhead_estimate:
```

---

## Change log — 2026-09-19 admission.applications civil profile

| Item | Value |
|------|-------|
| Class | Low |
| Table | `admission.applications` |
| Change | Additive civil-profile columns + nullable `grade_level_id` + FK `target_school_id` |
| Risk | Existing rows valid (nullable). New drafts validated in FormRequest. |
| Blueprint | Updated |

## Change log — 2026-09-19 admission stage query indexes

| Item | Value |
|------|-------|
| Class | Medium |
| Tables | `admission.application_periods`, `admission.applications` |
| Change | Additive BTREE `(school_id, academic_year_id, status)` and `(application_period_id, status, created_at, id)` |
| Evidence | Stage JOIN/filter/ORDER BY LIMIT; baseline Seq Scan at 200 rows (~0.25–0.4ms); indexes for multi-year / large cardinality |
| Risk | Write overhead low; no lock beyond CREATE INDEX |
| Blueprint / indexing-matrix | Updated |

---

## Change log — 2026-09-25 student_documents admission ID types

| Item | Value |
|------|-------|
| Class | Low |
| Table | `students.student_documents` |
| Change | Expand CHECK `document_type` to include 11–19 (national ID / residence / graduation scans) |
| Risk | Additive constraint widen only; existing rows remain valid |
| Blueprint | Updated |

---

## Change log — 2026-09-25 admission subject grades + personal photo

| Item | Value |
|------|-------|
| Class | Low |
| Tables | `admission.applications`, `students.student_documents` |
| Change | Add nullable `mathematics_grade` / `physics_grade` (0–100 CHECK); expand `document_type` to include 20 (personal photo) |
| Risk | Additive only; no index (not filtered/joined yet); existing rows remain valid |
| Blueprint | Updated |

---

## Change log — 2026-09-25 admission request_kind (قناة القبول)

| Item | Value |
|------|-------|
| Class | Low |
| Table | `admission.applications` |
| Change | Re-add `request_kind` SMALLINT NOT NULL DEFAULT 1 CHECK (1,2) for admission-channel filter/stats; backfill 2 when intended grade present |
| Risk | Additive; dialog filter/counts only (≤500 rows); no index yet |
| Blueprint | Updated |

---

## Change log — 2026-09-25 admission request_kind semantics (قناة القبول)

| Item | Value |
|------|-------|
| Class | Low |
| Table | `admission.applications.request_kind` |
| Change | Correct channel semantics: 1=academic→vocational transfer, 2=vocational intake (align UI/form; default 2) |
| Risk | Existing inverted rows already match new semantics; no mass UPDATE |
| Blueprint | Updated |

---

## Change log — 2026-09-26 admission rejection_reason

| Item | Value |
|------|-------|
| Class | Low |
| Table | `admission.applications` |
| Change | Add nullable `rejection_reason` TEXT; backfill from `notes` where status=Rejected |
| Risk | Additive only; no index (displayed in ≤800-row roster dialog) |
| Blueprint | Updated |

---

## Change log — 2026-09-26 admission withdrawal_reason

| Item | Value |
|------|-------|
| Class | Low |
| Table | `admission.applications` |
| Change | Add nullable `withdrawal_reason` TEXT; backfill from `notes` where status=Withdrawn |
| Risk | Additive only; roster/dialog display only |
| Blueprint | Updated |

---

## Change log — 2026-09-28 students drop specialization/stage, add class_name + admission parity

| Item | Value |
|------|-------|
| Class | Medium |
| Table | `students.students` |
| Change | Drop columns `specialization_name`, `stage_name` (user-approved). Add `class_name` string(100) nullable, `father_occupation` string(100) nullable, `mother_occupation` string(100) nullable, `administrative_unit` smallint nullable (CHECK 1-3), `graduation_year` smallint nullable (CHECK 1950-2100), `previous_gpa` decimal(5,2) nullable (CHECK 0-100), `previous_study_track` smallint nullable (CHECK 1-5), `mathematics_grade` decimal(5,2) nullable (CHECK 0-100), `physics_grade` decimal(5,2) nullable (CHECK 0-100). Types match `admission.applications`. |
| Risk | Column drops affect enrollment repo student-label sync (removed writes). Admission convert/register mappers updated to copy new parity fields. All student DTOs, commands, handlers, rules, UI updated. |
| Blueprint | Updated |

---

## Change log — 2026-09-28 students.request_kind + admission parity/document backfill

| Item | Value |
|------|-------|
| Class | Low–Medium |
| Table | `students.students`, `students.student_documents` |
| Change | Add nullable `request_kind` SMALLINT CHECK (1,2). Backfill from linked `admission.applications` (parity columns + request_kind). Copy missing `application_documents` onto `student_documents` for converted students. Convert path now copies request_kind, class_name, and documents. |
| Risk | Additive column + data backfill only; no drops. Document copy skips existing active types. |
| Blueprint | Updated |

---

## Change log — 2026-09-28 drop students class_name + section_name

| Item | Value |
|------|-------|
| Class | Medium |
| Table | `students.students` |
| Change | User-approved drop of `class_name` and `section_name`. Placement remains on `enrollment.*`; `admitted_class_name` retained as admission grade level (المستوى الدراسي). Enrollment sync no longer writes dropped columns. |
| Risk | Destructive column drop on denormalized student labels only; enrollment class/section unchanged. |
| Blueprint | Updated |

---

## Change log — 2026-09-28 enrollment sheet UI parity (no schema change)

| Item | Value |
|------|-------|
| Class | None (UI + API wiring only — no migration) |
| Table | `enrollment.enrollments` (no column change) |
| Change | `enrollment-record-form.tsx` (`EnrollmentRecordForm`/`EnrollmentViewDialog`) rewritten to match the student sheet dialog chrome (hero, `WindowControls`, `SheetSection`, many-scroller, Cancel/Edit/Save). UI now edits `status`, `effective_from`, `effective_to`, `branch_id`, `department_id` (labelled الاختصاص), `class_id`, `section_id` directly on the enrollment record; quad name, gender, academic year, and student id (`student_code \|\| student_id`) are display-only, join-derived fields — never written back. Removed the `grade_level`/`stage_name` inputs and the editable name/gender fields, and removed the `PUT /students/{id}` side-write that previously carried `department_name`/`branch_id`/`stage_name`/`section_name` back onto the denormalized student row. Save is now: `POST /enrollments/bulk-status` (only if status changed) then `PUT /enrollments/{id}` with `class_id`, `section_id`, `branch_id`, `department_id`, `specialization_id` (echoes existing `enrollment.specialization_id` unchanged — not user-editable), `effective_from`, `effective_to`/`clear_effective_to`, `academic_year_id` (unchanged, echoed). `EnrollmentController::update()` was already validating `branch_id`/`department_id`/`effective_from`/`effective_to`/`clear_effective_to`/`academic_year_id`/`gender` in `UpdateEnrollmentPlacementRequest` and `UpdateEnrollmentPlacementCommand`/`UpdateEnrollmentPlacementHandler` already supported them, but the controller was not forwarding those validated fields into the command (dead wiring — only `class_id`/`section_id`/`specialization_id` were applied). Fixed the controller to forward all validated fields (`updateBranch`/`updateDepartment` gated on `$request->has(...)`, `clearEffectiveTo` via `$request->boolean(...)`) so the enrollment-only PUT is now the single source of truth for placement + effective-date changes. |
| Risk | Low — no DDL. Closes a real functional gap (branch/department/date edits were silently dropped by the controller before this fix) and removes a redundant, indirect write path through the student row. `enrollment.branch_id`/`enrollment.department_id`/`enrollment.specialization_id` remain the FK-correct source of truth for placement; `students.branch_id`/`students.department_name` are denormalized labels still synced by `EloquentEnrollmentRepository::updatePlacement()` (unchanged), not by the UI. |
| Blueprint | Noted under `enrollment.enrollments` — see `database-blueprint.md` (no column added/dropped; FK model already correct). |

---

## Change log — 2026-09-29 drop enrollment specialization_id + enrollment_number

| Item | Value |
|------|-------|
| Class | Medium (destructive column drop) |
| Table | `enrollment.enrollments` |
| Change | Dropped `specialization_id` (vocational; UI الاختصاص = `department_id`) and `enrollment_number`. Stage/grade were never enrollment columns (join via class → grade_levels) — removed from enrollment sheet UI only. Writers/readers updated to stop selecting/writing dropped columns; API/Inertia update always passes `specializationId: null`. Save path: `PUT /enrollments/{id}` + Inertia `only` reload; list `onSaved` merges by `student_id` to handle mid-year supersede id change. |
| Risk | Medium — irreversible column drop; synthetic `ENR-{id}` remains in DTO/API for backward-compatible response shape only. |
| Blueprint | Updated |

---

## Change log — 2026-09-29 graduation denorm triggers (enrollment specialization drop follow-up)

| Item | Value |
|------|-------|
| Class | Low (function replace only) |
| Table | `graduation.completion_outcomes` / `graduation.graduation_awards` triggers |
| Change | `enforce_completion_outcome_denorm` / `enforce_graduation_award_denorm` no longer SELECT `enrollment.enrollments.specialization_id` (column dropped). Insert denorm still validates school/student/academic_year against enrollment; graduation-row `specialization_id` remains optional/immutable on UPDATE. |
| Risk | Low — restores graduation insert path broken by prior column drop. |
| Blueprint | No table column change |

---

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md)
- [zero-downtime-migrations.md](./zero-downtime-migrations.md)
- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
