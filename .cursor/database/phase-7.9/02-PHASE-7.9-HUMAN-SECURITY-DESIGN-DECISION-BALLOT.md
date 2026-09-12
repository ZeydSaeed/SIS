# MASTER PHASE 7 — PHASE 7.9
# HUMAN SECURITY DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Human «استمر» → RECOMMENDED SET APPLIED
```

### HD-7.9-001 — Permission

```text
[x] A — Dedicated portal.scopes.manage (+ role portal_scopes_manager)
[ ] B — Reuse security.manage_users alone
```

### HD-7.9-002 — Operations

```text
[x] A — Link + Unlink + List-by-user
[ ] B — Link only
```

### HD-7.9-003 — Student link rule

```text
[x] A — Student exists AND has enrollment in current school
[ ] B — Student exists globally (any school) — REJECTED
```

### HD-7.9-004 — Guardian link rule

```text
[x] A — Guardian exists AND linked via student_guardians to ≥1 student enrolled in current school
[ ] B — Guardian exists only
```

### HD-7.9-005 — Unlink semantics

```text
[x] A — Hard-delete scope row (not academic ledger; unique binding only)
[ ] B — Soft-delete column (requires migration) — DEFER
```

### HD-7.9-006 — Idempotency

```text
[x] A — Link requires Idempotency-Key; Unlink idempotent if already absent
```

### HD-7.9-007 — Audit

```text
[x] A — PrivilegeChanged on link/unlink
```

### HD-7.9-008 — Phase 8

```text
[x] A — HOLD
```

```text
BALLOT: RECORDED / APPLIED
```
