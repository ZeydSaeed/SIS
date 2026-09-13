# PHASE COM — U06 SCHEMA CHANGE IMPACT

---

```text
Change: NONE (application-only)
Tables: communication.messages (existing status + sent_at)
Risk: LOW
```

## Checklist

- [x] No migration
- [x] No column ALTER
- [x] Transition Queued→Sent only via MarkMessageSent
- [x] Fail-closed if missing / wrong school / not Queued
- [x] Blueprint HTTP note updated
- [x] PG HTTP tests
