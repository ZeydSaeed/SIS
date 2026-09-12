# PHASE FIN — DESIGN LOCK (student_fees obligation slice)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: FIN-OBLIGATIONS (student_fees)
Ballot: 05 LOCKED
```

## In

```text
- Physicalize finance.student_fees
- ADD school_id for FORCE RLS
- Reject hard DELETE
- AssignStudentFee (idempotent) + ListStudentFees
- amount snapshot from fee_type; optional amount override (>= 0)
- UNIQUE(school_id, enrollment_id, fee_type_id, academic_year_id)
- status DEFAULT Unpaid(1)
- Reuse finance.view / finance.manage
```

## Out (still HOLD)

```text
- payments / transactions
- refunds / voids / cancel HTTP
- payment_method enum / gateway
```

## Units

| Unit | Name | Status |
|------|------|--------|
| FIN-U01/U02 | fee_types catalog | CLOSED |
| FIN-U03 | Schema + RLS student_fees | CLOSED |
| FIN-U04 | Assign + List HTTP | CLOSED |
| FIN-U05 | Closure (obligations slice) | CLOSED (see 09) |
| FIN-PAY | payments/transactions | payments CLOSED (see 14); transactions HOLD |
