# MASTER PHASE 7 — PHASE 7.4
# HUMAN IMPLEMENTATION AUTHORIZATION — 7.4-U02

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION

Unit:
7.4-U02 — CalculateTermResult (operational)

Date:
2026-09-12

Predecessor:
7.4-U01 CLOSED / ACCEPTED

Design Lock:
03 LOCKED — HD-7.4-004…008, 014, 015

Status:
GRANTED
```

---

## 1. Authorized work

```text
AUTHORIZED:
  - Domain policy helpers for operational eligibility (Entered|Finalized + current)
  - Application CalculateTermResultCommand / Handler / Result
  - Weighted total from current grades + exam_type.weight_percentage
  - Persist operational version row on results.term_results
  - Idempotency key + outbox event (TermResultCalculated)
  - Unit + Feature tests (PG where DB asserts needed)
  - architecture:validate --fitness

NOT AUTHORIZED:
  - FinalizeTermResult (U03)
  - RebuildTermResult (U04)
  - Annual results
  - HTTP Results APIs
  - GPA / letter / ranking
  - Auto-finalize on term close
```

---

## 2. Behavior lock

```text
Operational Calculate:
  - is_official = false
  - lifecycle_status = Calculated (1)
  - is_current_operational = true (supersede prior ops current → Superseded)
  - Weights: if sum ≠ 100 ±0.01 → fail-closed (same rule as official for consistency)
  - Grades: is_current + status ∈ {Entered, Finalized}; exclude Voided
  - Absent: exclude from num/den; incomplete=true if required absent unresolved
  - Rounding: NUMERIC(8,2) half-up
  - No HTTP
```

---

## 3. STOP

```text
7.4-U02: IMPLEMENTATION AUTHORIZED
Continue → application-feature skill → implement
```
