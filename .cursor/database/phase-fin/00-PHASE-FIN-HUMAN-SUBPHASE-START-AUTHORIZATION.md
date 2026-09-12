# PHASE FIN — FINANCE FEE CATALOG
# HUMAN SUBPHASE START AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: Phase FIN — fee_types catalog ONLY (Create/List)
Rejected this slice: student_fees / payments / transactions (money movement)
Rejected: Ranking/PDF portal (locked deferred)
Status: GRANTED
```

## Why fee_types first

| Option | Verdict |
|--------|---------|
| finance.fee_types | Catalog · school_id present · no money movement |
| student_fees / payments / transactions | HOLD — needs payment/refund/ledger ballot |
| communication.* | Lower dependency on money SSOT but FIN was next after DOC |

## Absolute holds

```text
- No invent payment methods / refunds / double-entry without ballot
- No hard-delete fee catalog rows
- Money columns NUMERIC(12,2)
- Idempotency on CreateFeeType
```
