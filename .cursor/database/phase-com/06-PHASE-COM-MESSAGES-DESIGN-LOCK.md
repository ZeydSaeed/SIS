# PHASE COM — DESIGN LOCK (messages queue)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: COM-MESSAGES-QUEUE
Ballot: 05 LOCKED
```

## In

```text
- Physicalize communication.messages (+ school_id)
- FORCE RLS + reject hard DELETE
- Unpartitioned
- QueueMessage (idempotent, status=Queued) + ListMessages
- Reuse communication.view / communication.manage
- channel: same as templates (1/2/3/9)
- template_id optional FK
- DB idempotency_key UNIQUE + X-Idempotency-Key app store
```

## Out

```text
- SMTP/SMS/push providers
- Mark sent / retry HTTP
- notification_jobs
- Partition
```

## Units

| Unit | Name | Status |
|------|------|--------|
| COM-U04 | Schema + RLS messages | CLOSED |
| COM-U05 | Queue + List HTTP | CLOSED |
| COM-U06 | Closure | CLOSED (see 09) |
| COM-PROVIDER | Actual send | HOLD |
