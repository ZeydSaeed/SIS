# PHASE TV — WORKSHOPS FINAL CLOSURE GATE

---

```text
Slice: TV-WORKSHOPS
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
Human: «استمر»
```

## Unit matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| TV-U12 | workshops schema + Create/List | CLOSED |
| TV-U13 | This gate | CLOSED |

## Invariants

| ID | Status |
|----|--------|
| INV-TV-WS-01 safety_capacity ≤ capacity (DB+app) | PASS |
| INV-TV-WS-02 FORCE RLS school_id | PASS |
| INV-TV-WS-03 No hard DELETE | PASS |
| INV-TV-WS-04 Catalog only (no assignment wave) | PASS |

## Recommended next

```text
1) Real SMTP provider ballot, OR
2) section_batches ballot, OR
3) Workshop assignment enforce vs safety_capacity
```

```text
PHASE TV WORKSHOPS FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Workshops safety catalog live. section_batches HOLD.
```
