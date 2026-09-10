# Phase 3C.14 — Policy / Database Alignment

## Locked identity (must not be changed)

```text
Policy (HD-39 APPROVED)
  → Logical identity: school_id + enrollment_id (+ program/context via enrollment)
  → Constraint: UNIQUE (school_id, enrollment_id) on completion_outcomes / graduation_awards
  → RLS: school_id fail-closed + FORCE
  → Versioning: versions under outcome/award parents
  → Application: commands scoped to school + enrollment — never student_id-only
```

**Alignment:** PASS — LIVE schema matches HD-39. Do not “fix” identity to student-level.

## Completion ≠ Graduation (HD-19)

```text
Policy → separate completion vs award lineages
  → Tables: completion_* vs graduation_awards*
  → No collapse into students.status
```

**Alignment:** PASS.

## Evaluation framework vs content

| Layer | Status |
|-------|--------|
| Framework tables (policy/requirement/evidence/eval) | Present — supports HD-20/21 structure |
| JSONB content schema | Nullable — D-3C10-007; **no authoritative rule content** |
| Grades SSOT | Remains `exams.student_grades` (DL-018) — Graduation only references evidence |

**Criterion classification (evaluation content):**

| Criterion | Class |
|-----------|-------|
| Versioned EligibilityPolicyVersion pin | AUTHORITATIVE (framework) |
| RequirementDefinitionVersion rows | CONFIGURABLE structure / POLICY-DEPENDENT content |
| EvidenceSet / EvidenceItem | AUTHORITATIVE mechanism; sources POLICY-DEPENDENT |
| RequirementEvaluation results | DERIVED from evidence+policy version; versioned with outcome version |
| Grades / credits / attendance / standing as gates | **NOT DEFINED** as Graduation defaults (HD-22 no GPA; lists open) |
| Blueprint min_gpa / credits | **NON-AUTHORITATIVE** — do not use |

**Evaluation result mutability (approved model):**

```text
mutable in place: FORBIDDEN for official versions (DL-019 / HD-35)
immutable official rows + supersedable via new version: REQUIRED
```

**Alignment:** PASS for structure; PARTIAL until content authored (no schema change required to store content later in JSONB/requirement rows).

## Human approval (HD-31 model)

```text
Policy → machine must not auto-approve
  → graduation_approvals table + decision_status
  → decided_by opaque BIGINT (D-3C10-006)
  → Application must refuse auto ELIGIBLE→APPROVED
```

**Alignment:** PASS for model. Role matrix absence does **not** require schema change — blocks **permission constants**, not DDL.

## Award (HD-32 structure)

```text
Separate award tables + versions + partial UNIQUE current issued
```

**Alignment:** PASS. Attribute catalogs NOT LOCKED — nullable columns OK; no destructive schema needed.

## StudentStatus projection (DL-022)

```text
Policy → projection only
  → No graduation SSOT table on students (D-3C10-008 CLOSED)
  → Multi-enrollment flip rules NOT LOCKED → do not encode in DB constraints
```

**Alignment:** PASS — intentional non-constraint. Auto-sync remains application policy.

## Policies that would require schema change

| Hypothetical | Status |
|--------------|--------|
| Student-level-only graduation identity | **CONFLICTS HD-39** — STOP; do not alter UNIQUE grain |
| Soft-delete official versions | **CONFLICTS DL-019** — STOP |
| Auto-approve without approval row | **CONFLICTS HD-31** — STOP |
| New StudentStatus projection table | **CONFLICTS D-3C10-008** — STOP |

```text
SCHEMA CHANGE REQUIRED BY LOCKED POLICY: NONE
UNAPPROVED SCHEMA CHANGE FORBIDDEN
```

## Verdict

```text
POLICY / DATABASE ALIGNMENT: PASS
```

(with content/roles remaining application blockers, not DDL defects)
