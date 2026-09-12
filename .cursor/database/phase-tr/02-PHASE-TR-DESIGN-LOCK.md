# PHASE TR — DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 01 RECOMMENDED SET APPLIED
```

## In

```text
- transfers.transfer_requests + transfer_records LIVE
- Dual-school FORCE RLS (from_school_id OR to_school_id)
- transfer_records gains from_school_id + to_school_id for RLS
- Status: 1 Pending, 2 Approved, 3 Rejected, 4 Completed, 5 Cancelled
- HTTP: Create / List / Approve / Reject
- Permissions: transfers.view / transfers.manage
```

## Out

```text
- PDF / portal ranking (locked deferred elsewhere)
- Cross-year bulk transfer engine
- Auto subject remapping on transfer
```

## Invariants

| ID | Rule |
|----|------|
| INV-TR-01 | from_school_id ≠ to_school_id |
| INV-TR-02 | Create only at from_school context |
| INV-TR-03 | Approve/Reject only at to_school context + Pending |
| INV-TR-04 | No hard delete |
| INV-TR-05 | Complete only at to_school + Approved; closes source as TRANSFERRED |

## Units

| Unit | Name | Status |
|------|------|--------|
| TR-U01 | Physicalize + RLS | CLOSED |
| TR-U02 | Staff HTTP request lifecycle | CLOSED |
| TR-U03 | CompleteTransfer | CLOSED |
| TR-U04 | CancelTransferRequest | CLOSED |
| TR-U05 | Final Closure Gate | CLOSED WITH CONDITIONS |
