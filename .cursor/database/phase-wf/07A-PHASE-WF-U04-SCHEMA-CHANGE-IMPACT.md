# PHASE WF-U04 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE workflow.approval_requests + school_id + FORCE RLS
Risk: LOW–MEDIUM (runtime rows; no decision engine)
Date: 2026-09-12
```

## Checklist

```text
[x] Ballot locked
[x] school_id ADD
[x] FK flow_id RESTRICT
[x] Reject hard DELETE
[x] FORCE RLS
[ ] Approve/Reject — NOT in this unit
```
