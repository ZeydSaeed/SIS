# PHASE TR — HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Absolute continuation — RECOMMENDED SET
```

## HD-TR-001 — Scope

```text
[x] A — Physicalize + Create/List/Approve/Reject request HTTP; CompleteTransfer DEFERRED
[ ] B — Schema only
[ ] C — Full complete (create destination enrollment) now — FORBIDDEN without dedicated AuthZ
```

## HD-TR-002 — RLS model

```text
[x] A — FORCE RLS: current_school_id IN (from_school_id, to_school_id)
[ ] B — Single owning school_id only — REJECTED (destination school blind)
```

## HD-TR-003 — Create authority

```text
[x] A — Create only when from_school_id = context school; enrollment must belong to from school+year
[ ] B — Either school may create — REJECTED for v1
```

## HD-TR-004 — Approve/Reject authority

```text
[x] A — Destination school (to_school_id = context) only, status Pending → Approved|Rejected
[ ] B — Either school — REJECTED for v1
```

## HD-TR-005 — transfer_records

```text
[x] A — Physicalize now with from_school_id + to_school_id (RLS delta); writers only on Complete (later)
[ ] B — Defer table — REJECTED (blueprint pair; empty OK)
```

## HD-TR-006 — Hard delete

```text
[x] A — Reject hard DELETE on both tables
```
