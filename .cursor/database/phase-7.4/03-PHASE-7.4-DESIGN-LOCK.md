# MASTER PHASE 7 — PHASE 7.4
# DESIGN LOCK

---

```text
Document Type:
SUBPHASE DESIGN LOCK

Subphase:
PHASE 7.4 — RESULTS / ACADEMIC AGGREGATION

Date:
2026-09-12

Status:
LOCKED

Ballot:
02 — RECORDED / APPLIED

P7-D2:
RESOLVED — ownership Phase 7.4 / 7.5

Implementation:
NOT AUTHORIZED by Design Lock alone — requires unit AuthZ
```

---

## 1. Scope Locked

### In Phase 7.4

```text
- results.term_result_versions (physical) + FORCE RLS
- CalculateTermResult (operational)
- FinalizeTermResult (official)
- RebuildTermResult (deterministic)
- Annual results equivalents (later units in same subphase)
- School isolation + academic_year_id scoping
- Version / supersede lineage (no silent official mutate)
```

### Out of Phase 7.4 (→ 7.5 or later)

```text
- GPA engines / gpa history tables
- Ranking snapshots
- Transcript issuance / legal immutability (3D packaging)
- Letter-band conversion tables
- Results HTTP writers (unless future unit AuthZ)
- Phase 7.6 student/guardian read models
- Phase 8
```

---

## 2. P7-D2 Resolution (amends Master Lock deferral)

```text
BEFORE: P7-D2 = DEFERRED — OWNERSHIP DECISION REQUIRED
AFTER:  P7-D2 = LOCKED — owned by Phase 7.4 (Term/Annual) + Phase 7.5 (GPA/Ranking/Transcript)

NOT owned by Phase 18 Reporting MVs.
```

Master Phase 7 Final Closure remains CLOSED WITH CONDITIONS; this opens the **conditional track**, not a reopen of 7.1–7.3.

---

## 3. Absolute invariants (LOCKED)

| ID | Rule |
|----|------|
| INV-74-01 | Grade SSOT = `exams.student_grades` only (DL-001) |
| INV-74-02 | `results.*` NEVER stores authoritative raw marks as a writable second ledger |
| INV-74-03 | Official versions: version/supersede only — no silent in-place mutate (DL-007) |
| INV-74-04 | Rebuild must be deterministic from grades + structure + pinned policy versions (DL-016) |
| INV-74-05 | Tenant: `school_id` on every results row + FORCE RLS |
| INV-74-06 | Every result scoped to `academic_year_id` |
| INV-74-07 | No DEFAULT partition introduced on `student_grades` |
| INV-74-08 | `exam.session.cancel` remains FORBIDDEN / ABSENT |

---

## 4. Policy locks (from ballot)

| Topic | Lock |
|-------|------|
| Weights (HD-03) | Official: sum to 100 ±0.01 or fail-closed |
| Status eligibility (HD-04) | Official: current + Finalized; Ops: current + Entered/Finalized; never Voided |
| Absent (HD-05) | Exclude from weighted avg; incomplete if required Absent unresolved |
| Retake (HD-06) | `is_current=true` only |
| Dataset (HD-18) | Official finalize requires complete eligible set |
| Term closed (HD-17) | No auto-finalize |
| Letter/GPA | Excluded from 7.4 v1 columns |
| Rounding | NUMERIC(8,2) half-up |
| Pass/fail | Derived when threshold known; else NULL |

---

## 5. Logical → physical (U01 target)

```text
Table (v1 name): results.term_result_versions

Business identity on version row:
  school_id, enrollment_id, student_id, academic_year_id, term_id, subject_id, result_version

Lifecycle status SMALLINT:
  1=Calculated, 2=Finalized, 3=Superseded
  (NOT_CALCULATED is absence of row / or status 0 if needed — prefer no placeholder rows)

Flags:
  is_official BOOLEAN
  is_current_operational BOOLEAN  -- at most one ops current per identity
  is_current_official BOOLEAN     -- at most one official current per identity

Metrics (no letter/GPA):
  weighted_total NUMERIC(8,2) NULL
  pass_fail SMALLINT NULL         -- NULL/0/1 per policy
  incomplete BOOLEAN NOT NULL DEFAULT false

Provenance:
  source_fingerprint VARCHAR(...)
  calculation_version INT
  policy_pins JSONB or explicit version columns (minimal v1: JSONB policy_pin)
  calculated_at TIMESTAMPTZ
  finalized_at TIMESTAMPTZ NULL
  superseded_at TIMESTAMPTZ NULL
  correlation_id UUID/VARCHAR
  created_by BIGINT NULL

PK: BIGINT GENERATED ALWAYS AS IDENTITY
```

Exact DDL refined in unit AuthZ + database-change skill; this lock freezes **intent**, not every column name.

---

## 6. Unit plan (authorized only after per-unit AuthZ)

| Unit | Name | Status |
|------|------|--------|
| **7.4-U01** | Term result versions schema + FORCE RLS | NEXT AuthZ |
| **7.4-U02** | CalculateTermResult (operational) | NOT AUTHORIZED |
| **7.4-U03** | FinalizeTermResult (official) | NOT AUTHORIZED |
| **7.4-U04** | RebuildTermResult | NOT AUTHORIZED |
| **7.4-U05+** | Annual results | NOT AUTHORIZED |

---

## 7. STOP

```text
PHASE 7.4 DESIGN LOCK: LOCKED

P7-D2: RESOLVED

NEXT:
  04 — HUMAN IMPLEMENTATION AUTHORIZATION REQUEST for 7.4-U01 ONLY
```
