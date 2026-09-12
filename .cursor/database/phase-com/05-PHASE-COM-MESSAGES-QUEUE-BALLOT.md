# PHASE COM — MESSAGES QUEUE BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-12
Human: «استمر»
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-COM-SEND-001 | Open messages now? | A yes queue · B hold | **A queue only** |
| HD-COM-SEND-002 | school_id? | A ADD · B derive | **A ADD** |
| HD-COM-SEND-003 | Actual SMTP/SMS send? | A yes · B HOLD | **B HOLD** |
| HD-COM-SEND-004 | notification_jobs bulk? | A open · B HOLD | **B HOLD** |
| HD-COM-SEND-005 | Partition year-1? | A yes · B no until measured | **B no** |
| HD-COM-SEND-006 | status v1 | — | **1 Queued only on create** |
| HD-COM-SEND-007 | Hard delete | A allow · B reject | **B reject** |
| HD-COM-SEND-008 | recipient_type allowlist | — | **student · teacher · user · guardian** |

## Implications

```text
IN: messages table + QueueMessage + ListMessages (status=Queued)
OUT: providers, mark-sent HTTP, jobs, partition
```
