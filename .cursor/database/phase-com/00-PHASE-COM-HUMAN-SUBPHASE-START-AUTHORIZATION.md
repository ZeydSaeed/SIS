# PHASE COM — COMMUNICATION TEMPLATES
# HUMAN SUBPHASE START AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر» (best path)
Selected: Phase COM — notification_templates catalog Create/List
Rejected this slice: messages / notification_jobs (send pipeline)
Rejected: FIN money movement (ballot HOLD)
Rejected: Ranking/PDF portal (locked deferred)
Status: GRANTED
```

## Why templates first

| Option | Verdict |
|--------|---------|
| notification_templates | Catalog · no outbound send · low risk |
| messages / jobs | HOLD — queue + channel providers + PII |
| workflow.* | Separate AuthZ |
| FIN payments | Ballot required |

## Absolute holds

```text
- No invent SMTP/SMS gateway without ballot
- No send-on-HTTP
- ADD school_id for FORCE RLS (blueprint lacked tenant column)
- UNIQUE(school_id, code) not global code
```
