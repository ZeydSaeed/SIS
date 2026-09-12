# PHASE FIN — DESIGN LOCK (payments slice)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: FIN-PAYMENTS
Ballot: 10 LOCKED
```

## In

```text
- Physicalize finance.payments (+ school_id)
- FORCE RLS + reject hard DELETE
- RecordPayment (idempotent) + ListPayments
- payment_method: 1 Cash, 2 BankTransfer, 3 Card, 9 Other
- Reject overpayment (amount ≤ remaining)
- Rollup student_fees.status → Partial(2) / Paid(3)
- Reuse finance.view / finance.manage
```

## Out

```text
- finance.transactions
- Refunds / void payment
- Gateways / portal
```

## Units

| Unit | Name | Status |
|------|------|--------|
| FIN-U05 | Schema + RLS payments | CLOSED |
| FIN-U06 | Record + List HTTP | CLOSED |
| FIN-U07 | Closure (payments slice) | CLOSED (see 14) |
| FIN-TXN | transactions ledger | CLOSED (see 19) |
