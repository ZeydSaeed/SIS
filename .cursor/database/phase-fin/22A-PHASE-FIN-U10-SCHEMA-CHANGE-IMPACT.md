# PHASE FIN — U10 SCHEMA CHANGE IMPACT

---

```text
Change: ALTER finance.payments (+status/void columns); widen transactions type CHECK
Type: ALTER additive + constraint widen
Risk: MEDIUM (money integrity — soft void + reverse ledger only)
```

## Checklist

- [x] No DROP of payment/transaction history rows
- [x] No silent amount rewrite
- [x] Posted payments default status=1 for existing rows
- [x] sumByStudentFee excludes Voided
- [x] Ledger reverse append-only
- [x] Blueprint updated
- [x] PG HTTP tests
- [x] Reject DELETE triggers unchanged

## Blast radius

```text
- RecordPayment / ListPayments / VoidPayment
- FinanceLedgerAppender
- student_fees.status rollup
```
