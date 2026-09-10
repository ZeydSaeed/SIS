# 03 — DENORMALIZED IDENTITY REMEDIATION (F-11A-002)

**Triggers NOT created.** Spec only.

## Invariant

```text
Graduation.school_id = Enrollment.school_id
```

for every row that references that enrollment via `(enrollment_id, school_id)`.

Where `student_id` / `academic_year_id` / `specialization_id` are denormalized, they MUST equal the referenced enrollment’s columns.

---

## Constraint vs trigger

| Invariant | Mechanism |
|-----------|-----------|
| `(enrollment_id, school_id)` matches enrollments | **Composite FK** → `enrollment.enrollments(id, school_id)` RESTRICT — **constraint-enforced** |
| `(enrollment_id, academic_year_id)` matches when year denorm present | **Composite FK** → LIVE `enrollments(id, academic_year_id)` unique — **constraint-enforced** (preferred) |
| `student_id` = enrollment.student_id | **Trigger** — no LIVE composite unique `(id, student_id)` proven for FK — **trigger-enforced** |
| `specialization_id` IS NOT DISTINCT FROM enrollment.specialization_id | **Trigger** — **trigger-enforced** |
| Child `school_id` = parent version/set `school_id` | **Trigger** on child INSERT/UPDATE — **trigger-enforced** |
| Cross-school enrollment pairing | Impossible under composite FK — **constraint-enforced** |

Do **not** add a trigger that only re-checks what the composite FK already guarantees.

---

## Matrix

| Table | Parent | Composite FK (enroll, school) | Trigger needed | INSERT checked | UPDATE checked | Cross-school impossible |
|-------|--------|-------------------------------|----------------|----------------|----------------|-------------------------|
| completion_outcomes | enrollments | YES | YES — student_id, specialization_id (+ year via FK if denorm) | YES | YES — reject identity column changes | YES |
| graduation_awards | enrollments | YES | YES — same denorm set | YES | YES — reject identity changes | YES |
| graduation_approvals | enrollments | YES | YES — if student/year/spec denorm present; else FK only | YES | YES — reject identity changes | YES |
| completion_outcome_versions | outcome + school | via outcome / school_id NOT NULL | YES — school_id must equal parent outcome.school_id | YES | YES — school_id immutable | YES (via parent) |
| evidence_sets | completion version | via parent | YES — school_id = parent | YES | school_id immutable | YES |
| evidence_items | evidence_set | via parent | YES — school_id = parent set | YES | school_id immutable | YES |
| requirement_evaluations | completion version | via parent | YES — school_id = parent | YES | school_id immutable | YES |
| graduation_award_versions | award | via parent | YES — school_id = parent award | YES | school_id immutable | YES |
| outcome_supersessions | versions | school_id denorm | YES — school matches both endpoints’ school | YES | immutable | YES |
| revocation_records | award version | school_id denorm | YES — school = parent version school | YES | immutable | YES |
| eligibility_* / requirement_definitions* | schools only | N/A (no enrollment) | NO enrollment trigger | school via FK/RLS | — | N/A |

---

## Trigger specifications (only where required)

### T-DENORM-OUTCOME — `graduation.completion_outcomes`

| Field | Spec |
|-------|------|
| Table | `graduation.completion_outcomes` |
| Timing | **BEFORE** |
| Events | **INSERT OR UPDATE** |
| Columns | On UPDATE: fire when `enrollment_id`, `school_id`, `student_id`, `academic_year_id`, or `specialization_id` change; prefer **reject any UPDATE** to these identity columns |
| Invariant | Row denorm equals `enrollment.enrollments` for `enrollment_id` |
| Failure | `RAISE EXCEPTION` — abort statement |
| school_id / enrollment_id UPDATE | **NOT PERMITTED** after insert (identity immutable) |
| vs composite FK | FK proves school↔enrollment pair; trigger proves student/spec (and year if not using year composite FK) |
| vs RLS | Trigger runs in statement; RLS still filters visible rows; migration/superuser caveat unchanged |

### T-DENORM-AWARD — `graduation.graduation_awards`

Same pattern as T-DENORM-OUTCOME.

### T-DENORM-APPROVAL — `graduation.graduation_approvals`

Same if denorm columns exist; if only composite enrollment FK + school_id, trigger may be limited to “forbid UPDATE of enrollment_id/school_id”.

### T-DENORM-CHILD-SCHOOL — evaluations / evidence_* / award_versions / supersessions / revocations

| Field | Spec |
|-------|------|
| Timing | BEFORE INSERT OR UPDATE |
| Invariant | `NEW.school_id` equals parent row `school_id` |
| Parent lookup | JOIN parent by FK id |
| Failure | RAISE EXCEPTION |
| Recursion | Read-only SELECT on parent; no UPDATE of parent — **no recursion** |

### Academic year

Prefer **constraint** `FOREIGN KEY (enrollment_id, academic_year_id) REFERENCES enrollment.enrollments(id, academic_year_id)` when column present — then trigger need not re-check year.

---

## F-11A-002 status

```text
F-11A-002: CLOSED
```

Closed as **explicit trigger/constraint matrix**. Not executed.
