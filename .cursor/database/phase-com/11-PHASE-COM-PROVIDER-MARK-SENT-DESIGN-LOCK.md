# PHASE COM — DESIGN LOCK (provider / mark-sent)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: COM-PROVIDER-MARK-SENT
Ballot: 10 LOCKED
```

## In

```text
- Application port OutboundMessagePort
- Infrastructure LocalOutboundMessageAdapter (no network IO)
- MarkMessageSent command: Queued → Sent, set sent_at
- Domain event MessageSent via Outbox
- HTTP POST /api/v1/communication/messages/{id}/mark-sent
- Reuse manageCommunication + school context
```

## Out

```text
- SMTP/SMS/push credentials or SDKs
- Mark-failed HTTP
- notification_jobs
- Schema ALTER (status/sent_at already exist)
```

## Units

| Unit | Name | Status |
|------|------|--------|
| COM-U06 | Mark-sent + Local provider | AUTHORIZED |
| COM-U07 | Closure | PENDING |
