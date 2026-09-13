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
| U04 | Binary upload/download (local) | CLOSED — see 08/09 |
| Gate | Final Closure | CLOSED |

## Conditions

```text
- Metadata + local object storage (no BYTEA)
- Soft-delete / void status column still HOLD
- S3 / AV / PDF render HOLD
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
1) Real SMTP provider ballot, OR
2) Workshop safety capacity / section_batches, OR
3) Ranking/PDF portal ONLY after reopening 7.5/7.8
```

```text
PHASE DOC FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Metadata Register/List + local binary Upload/Download live.
See also 09-PHASE-DOC-BINARY-UPLOAD-FINAL-CLOSURE-GATE.md.
```
