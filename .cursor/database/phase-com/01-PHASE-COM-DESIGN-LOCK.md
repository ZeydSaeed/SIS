# PHASE COM — DESIGN LOCK (notification_templates)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: COM-TEMPLATES
```

## In

```text
- Physicalize communication.notification_templates
- ADD school_id (RLS delta — blueprint sketch lacked tenant column)
- UNIQUE(school_id, code) replacing global UNIQUE(code)
- FORCE RLS school isolation
- Reject hard DELETE
- CreateNotificationTemplate (idempotent) + ListNotificationTemplates
- Permissions: communication.view / communication.manage
- channel SMALLINT: 1=Email, 2=SMS, 3=InApp, 9=Other
```

## Out (HOLD)

```text
- communication.messages (partition + send status)
- communication.notification_jobs (bulk)
- Actual SMTP/SMS/push providers
- Template render engine / variable substitution execution
- Soft-deactivate HTTP (is_active present; deactivate deferred)
```

## Invariants

| ID | Rule |
|----|------|
| INV-COM-01 | Catalog only — DB stores templates, not outbound delivery |
| INV-COM-02 | school_id + FORCE RLS |
| INV-COM-03 | No hard delete |
| INV-COM-04 | Controllers thin; send never in HTTP |

## Units

| Unit | Name | Status |
|------|------|--------|
| COM-U01 | Schema + RLS templates | CLOSED |
| COM-U02 | Create + List HTTP | CLOSED |
| COM-U03 | Final Closure Gate | CLOSED (see 04 — templates slice) |
| COM-SEND | Messages/jobs/providers | messages queue CLOSED (see 09); providers HOLD |
