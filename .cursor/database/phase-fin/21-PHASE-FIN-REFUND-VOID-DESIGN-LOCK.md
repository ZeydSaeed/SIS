# PHASE FIN — DESIGN LOCK (refund / void payment)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: FIN-REFUND-VOID
Ballot: 20 LOCKED
```

## In

```text
- ALTER finance.payments: status, voided_at, voided_by + CHECK + index
- ALTER finance.transactions type CHECK to allow 3=PaymentRefunded
- VoidPayment: Posted→Voided; sum excludes voided; re-rollup fee status
- Append ledger PaymentRefunded (outstanding += payment.amount)
- HTTP POST /api/v1/finance/payments/{id}/void
- Domain event PaymentVoided
```

## Out

```text
- Partial refund amounts
- Silent mutate of payment.amount
- Cancel student_fee HTTP
- Partition / gateway / double-entry GL
```

## Units

| Unit | Name | Status |
|------|------|--------|
| FIN-U10 | Schema + VoidPayment HTTP | AUTHORIZED |
| FIN-U11 | Closure | PENDING |
