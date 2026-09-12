# PHASE 8.1 — QUALIFICATION VOID
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase 8.1 — VoidTeacherQualification
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
```

## Phase 8 / 8.1 remaining debt matrix

| Item | Status |
|------|--------|
| Teachers staff CRUD + subjects | CLOSED (Phase 8) |
| Qualifications Add/List | CLOSED (8.1-U01) |
| Qualifications Void | CLOSED (8.1-U03) |
| Binary document upload | HOLD |
| Body RLS on teachers / qualifications | HOLD |
| employee_code rename | HOLD |
| Multi-school teacher transfer | HOLD |
| Payroll / HR | OUT (separate phase) |

## Conditions

```text
- Soft-void only (no hard DELETE)
- Re-void rejected when not Active
- List returns voided rows with status=2
```

## Recommended next

```text
1) COM-PROVIDER / FIN refund ballots, OR
2) teacher body RLS ballot, OR
3) Ranking/PDF only after reopening 7.5/7.8
```

```text
PHASE 8.1 VOID FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Active→Voided with effective_to. Binary/RLS HOLD.
```
