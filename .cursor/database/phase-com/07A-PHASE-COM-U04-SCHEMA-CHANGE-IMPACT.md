# PHASE COM-U04 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE communication.messages + school_id + FORCE RLS (unpartitioned)
Risk: LOW–MEDIUM (queue rows; no outbound IO)
Date: 2026-09-12
```

## Checklist

```text
[x] Ballot locked
[x] school_id ADD
[x] UNIQUE(idempotency_key)
[x] No partition yet
[x] Reject hard DELETE
[x] FORCE RLS
[ ] providers / jobs — NOT in this unit
```
