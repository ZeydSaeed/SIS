# PHASE COM — PROVIDER / MARK-SENT BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» after Phase 8 body RLS
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-COM-PROV-001 | Open mark-sent now? | A yes · B hold | **A yes** |
| HD-COM-PROV-002 | Real SMTP/SMS? | A yes · B HOLD local port | **B LocalOutbound only** |
| HD-COM-PROV-003 | Async Laravel Job for send? | A yes · B sync in command | **B sync** (row-level; bulk jobs HOLD) |
| HD-COM-PROV-004 | Failure path HTTP? | A mark-failed · B HOLD | **B HOLD** (Local always succeeds) |
| HD-COM-PROV-005 | notification_jobs bulk? | A open · B HOLD | **B HOLD** |
| HD-COM-PROV-006 | Permission | A new · B reuse manage | **B communication.manage** |
| HD-COM-PROV-007 | Idempotency | A required · B none | **A X-Idempotency-Key** |
| HD-COM-PROV-008 | Re-mark already Sent | A reject · B noop success | **A reject** (not Queued) |

## Implications

```text
IN: OutboundMessagePort + LocalOutboundMessageAdapter
    MarkMessageSent (Queued→Sent + sent_at) + HTTP POST …/mark-sent
OUT: SMTP/SMS/push, mark-failed HTTP, notification_jobs, partition
```
