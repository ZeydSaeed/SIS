# PHASE DOC — DOCUMENTS
# FINAL CLOSURE GATE

---

```text
Subphase: Phase DOC — Document metadata registry
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
```

## Unit matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| U01 | Schema + FORCE RLS (+ school_id) | CLOSED |
| U02 | Register + List HTTP | CLOSED |
| Gate | Final Closure | CLOSED |

## Conditions

```text
- Metadata only (storage_key + hash) — no binary upload in HTTP
- No soft-delete / void status column in v1 (append-only + reject DELETE)
- No PDF render / certificate binary engine (still DEFERRED per 7.5)
```

## Absolute holds still apply

```text
- No exam.session.cancel
- No DEFAULT student_grades partition
- No second grade ledger
- No silent mutate of official/issued rows
- No invent PDF/4.0/letters without ballot
```

## Recommended next (best path)

```text
1) Phase FIN — Finance schema physicalization + ballot (fees/ledger HOLD until design)
   OR empty-schema backlog with explicit AuthZ
2) Ranking/PDF portal ONLY after reopening 7.5/7.8 ballots
3) HR / payroll later — separate AuthZ
```

```text
PHASE DOC FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Document metadata Register/List live under school FORCE RLS.
```
