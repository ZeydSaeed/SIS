# PHASE 8 — BODY RLS FINAL CLOSURE GATE

---

```text
Slice: 8-BODY-RLS
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
Human: «استمر»
```

## Unit matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| 8-U04 | Body FORCE RLS + PG isolation | CLOSED |
| 8-U05 | This gate | CLOSED |

## Invariants

| ID | Status |
|----|--------|
| INV-8-RLS-01 FORCE RLS on teachers + qualifications | PASS |
| INV-8-RLS-02 Isolation via teacher_schools membership | PASS |
| INV-8-RLS-03 Register INSERT without prior membership | PASS |
| INV-8-RLS-04 No school_id column on body tables | PASS |
| INV-8-RLS-05 Fail-closed without app.current_school_id | PASS |

## Deferred (unchanged)

```text
- Binary qualification upload
- employee_code rename
- Multi-school transfer flows
- Payroll / HR
```

## Recommended next

```text
1) COM-PROVIDER / mark-sent ballot, OR
2) FIN refund/void ballot, OR
3) DOC binary upload ballot
```

```text
PHASE 8 BODY RLS FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
```
