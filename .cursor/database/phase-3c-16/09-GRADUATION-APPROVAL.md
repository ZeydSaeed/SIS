# Phase 3C.16 — Graduation Approval

## Checks

1. School-scoped authority allow-list
2. Completion outcome version exists in school
3. `eligibility_status === 2` (eligible)
4. Evaluator ≠ Approver (version.created_by vs actor)
5. In-txn idempotency + outbox `GraduationApproved`
6. Insert approval row (immutable insert; no in-place rewrite)

## Not invented

- Approval level ladders / delegation trees (HD-31 OPEN)
- Permission names
- Silent machine approval
