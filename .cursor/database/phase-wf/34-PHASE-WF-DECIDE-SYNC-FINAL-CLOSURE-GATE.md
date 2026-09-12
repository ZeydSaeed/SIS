# PHASE WF — DECIDE→TRANSFER SYNC
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase WF — sync transfer status from final WF decide
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
```

## Workflow ↔ Transfers hook matrix

| Hook | Status |
|------|--------|
| CreateTransfer → open approval | CLOSED |
| Final WF Approved → transfer Approved | CLOSED |
| Final WF Rejected → transfer Rejected | CLOSED |
| Final WF → CompleteTransfer | HOLD |
| HTTP Approve at to_school (dual-path) | STILL LIVE |
| Promotion/Docs/Finance hooks | HOLD |

## Conditions

```text
- Soft-skip if transfer not Pending / disabled / error
- First-writer wins (HTTP approve or WF decide)
- Complete remains manual at to_school
```

## Recommended next

```text
1) Phase 8.1 void qualification, OR
2) COM-PROVIDER / FIN refund ballots, OR
3) Ranking/PDF only after reopening 7.5/7.8
```

```text
PHASE WF DECIDE-SYNC FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
WF terminal decide syncs transfer Approved/Rejected. Complete HOLD.
```
