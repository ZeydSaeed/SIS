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

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md)
- [zero-downtime-migrations.md](./zero-downtime-migrations.md)
- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
