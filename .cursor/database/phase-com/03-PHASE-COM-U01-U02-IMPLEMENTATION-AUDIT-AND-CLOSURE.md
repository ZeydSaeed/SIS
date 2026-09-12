# PHASE COM — U01+U02
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Units: COM-U01 (Schema+RLS templates) + COM-U02 (Create/List HTTP)
AuthZ: 02 GRANTED («استمر» best path)
Audit: PASS
Closure: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
```

## Delivered

```text
COM-U01:
- communication.notification_templates (+ school_id)
- UNIQUE(school_id, code)
- FORCE RLS + reject hard DELETE

COM-U02:
- CreateNotificationTemplate (idempotent)
- ListNotificationTemplates (?active_only=)
- Permissions: communication.view / communication.manage
- Routes:
  POST /api/v1/communication/templates
  GET  /api/v1/communication/templates
```

## Out of scope (HOLD)

```text
- messages / notification_jobs
- SMTP/SMS/push providers
- Send-on-HTTP / bulk jobs
```

## Validation

```text
PhaseComNotification* → PASS
architecture:validate --fitness → PASS
architecture:feature-check Communication → PASS
security:validate → PASS
```

```text
COM-U01: CLOSED / ACCEPTED
COM-U02: CLOSED / ACCEPTED WITH CONDITIONS
(condition: catalog only — no send pipeline)
```
