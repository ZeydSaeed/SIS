# MASTER PHASE 8 — TEACHERS
# FINAL CLOSURE GATE

---

```text
Subphase: Phase 8 — Teachers staff CQRS/HTTP
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
```

## Unit matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| U01 | Assignment RLS + Register + List/Show | CLOSED |
| U02 | Update / deactivate + subject assign/unlink | CLOSED |
| U03 | Final Closure Gate (staff core) | CLOSED |
| U04 | Body FORCE RLS (teachers + qualifications) | CLOSED — see 13/14 |

## Invariants

| ID | Status |
|----|--------|
| INV-8-01 No hard delete of teacher identity | PASS (subjects unlink allowed) |
| INV-8-02 School membership for school ops | PASS |
| INV-8-03 Register creates teacher_schools | PASS |
| INV-8-04 No new tables | PASS |
| INV-8-05 Thin controllers | PASS |

## Deferred conditions

```text
- Binary qualification upload
- employee_code rename
- Multi-school transfer flows
- Payroll / HR contracts / finance
```

## Recommended next

```text
1) COM-PROVIDER / mark-sent ballot, OR
2) FIN refund/void ballot, OR
3) DOC binary upload ballot
```

```text
PHASE 8 FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Staff core + body RLS (U04) complete. Phase 8.1 Qual Add/List/Void CLOSED.
See also 14-PHASE-8-BODY-RLS-FINAL-CLOSURE-GATE.md.
```