# PHASE FIN — DESIGN LOCK (fee_types catalog)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: FIN-CATALOG (fee_types only)
```

## In

```text
- Physicalize finance.fee_types
- FORCE RLS school isolation (school_id already in blueprint)
- Reject hard DELETE
- CreateFeeType (idempotent) + ListFeeTypes
- Permissions: finance.view / finance.manage
- Status SMALLINT: 1=Active (default); deactivate deferred
```

## Out (HOLD — ballot required)

```text
- finance.student_fees
- finance.payments
- finance.transactions (partition candidate)
- Refunds / voids / payment_method enum
- Gateway / portal payment
- Assign fee to enrollment
```

## Invariants

| ID | Rule |
|----|------|
| INV-FIN-01 | Catalog only — no balance mutation in this slice |
| INV-FIN-02 | school_id + FORCE RLS |
| INV-FIN-03 | amount NUMERIC(12,2); amount >= 0 |
| INV-FIN-04 | UNIQUE(school_id, code) |
| INV-FIN-05 | No hard delete |

## Units

| Unit | Name | Status |
|------|------|--------|
| FIN-U01 | Schema + RLS fee_types | CLOSED |
| FIN-U02 | Create + List HTTP | CLOSED |
| FIN-U03 | Final Closure Gate | CLOSED (see 04 — catalog slice) |
| FIN-BALLOT | Money movement ballot | LOCKED (see 05) |
