# PHASE FIN — DESIGN LOCK (transactions ledger)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: FIN-TXN
Ballot: 15 LOCKED
```

## In

```text
- Physicalize finance.transactions (+ school_id)
- FORCE RLS + reject hard DELETE
- Unpartitioned (partition deferred)
- Append-only from AssignStudentFee + RecordPayment
- ListFinanceTransactions (?student_id=&academic_year_id=)
- balance_after = running outstanding for student+year
- Reuse finance.view / finance.manage (list = view)
```

## Out

```text
- Partition
- POST create transaction
- Refunds / adjustments
```

## Units

| Unit | Name | Status |
|------|------|--------|
| FIN-U08 | Schema + RLS transactions | CLOSED |
| FIN-U09 | List HTTP + append hooks | CLOSED |
| FIN-U10 | Closure | CLOSED (see 19) |
