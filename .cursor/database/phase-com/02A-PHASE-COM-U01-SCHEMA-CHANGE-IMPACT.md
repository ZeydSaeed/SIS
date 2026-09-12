# PHASE COM-U01 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE communication.notification_templates + school_id ADD + FORCE RLS
Risk: LOW (catalog; no send)
Date: 2026-09-12
```

## Checklist

```text
[x] Blueprint updated (school_id delta + UNIQUE school+code)
[x] Schema communication
[x] PK BIGINT IDENTITY
[x] school_id FK RESTRICT
[x] channel SMALLINT CHECK
[x] body_template NOT NULL
[x] No hard-delete — reject trigger
[x] FORCE RLS
[ ] messages / notification_jobs — NOT in this unit
```

## Blast radius

```text
New schema + 1 table. No consumers yet. No partition.
```
