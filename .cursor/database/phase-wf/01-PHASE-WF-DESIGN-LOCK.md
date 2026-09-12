# PHASE WF — DESIGN LOCK (approval_flows)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: WF-FLOWS
```

## In

```text
- Physicalize workflow.approval_flows
- ADD school_id (RLS delta)
- FORCE RLS school isolation
- Reject hard DELETE
- CreateApprovalFlow (idempotent) + ListApprovalFlows
- Permissions: workflow.view / workflow.manage
- steps JSONB: non-empty array of {step:int>=1, role:string} — definition only
- Allowed entity_type v1: transfer_request, promotion_record, document_file, fee_type
```

## Out (HOLD)

```text
- workflow.approval_requests
- Approve/Reject/Advance step HTTP
- Auto-hook into Transfers/Promotion/Documents
- Deactivate HTTP (is_active present; deferred)
```

## Invariants

| ID | Rule |
|----|------|
| INV-WF-01 | Catalog only — steps are not executed |
| INV-WF-02 | school_id + FORCE RLS |
| INV-WF-03 | No hard delete |
| INV-WF-04 | Controllers thin |

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U01 | Schema + RLS flows | CLOSED |
| WF-U02 | Create + List HTTP | CLOSED |
| WF-U03 | Final Closure Gate | CLOSED (see 04 — flows slice) |
| WF-RUNTIME | approval_requests engine | create/list + decide CLOSED (see 09, 14); hooks/role HOLD |
