# PHASE WF-U01 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE workflow.approval_flows + school_id ADD + FORCE RLS
Risk: LOW (catalog; no runtime approvals)
Date: 2026-09-12
```

## Checklist

```text
[x] Blueprint updated (school_id delta)
[x] Schema workflow
[x] PK BIGINT IDENTITY
[x] school_id FK RESTRICT
[x] steps JSONB NOT NULL
[x] No hard-delete — reject trigger
[x] FORCE RLS
[ ] approval_requests — NOT in this unit
```
