# PHASE FIN — PAYMENTS BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-12
Human: «استمر»
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-FIN-PAY-001 | Open payments now? | A yes · B hold | **A yes** |
| HD-FIN-PAY-002 | school_id on payments? | A ADD · B derive only | **A ADD** |
| HD-FIN-PAY-003 | payment_method enum | Cash/Bank/Card/Other | **1 Cash · 2 BankTransfer · 3 Card · 9 Other** |
| HD-FIN-PAY-004 | Overpayment? | A allow · B reject | **B reject** (amount ≤ remaining) |
| HD-FIN-PAY-005 | Update student_fees.status? | A yes Partial/Paid · B leave Unpaid | **A yes** |
| HD-FIN-PAY-006 | Refunds / void payment? | A open · B HOLD | **B HOLD** |
| HD-FIN-PAY-007 | transactions ledger? | A open · B HOLD | **B HOLD** |
| HD-FIN-PAY-008 | Hard delete payments? | A allow · B reject | **B reject** |
| HD-FIN-PAY-009 | Idempotency | App store + DB UNIQUE column | **Both** (X-Idempotency-Key → payments.idempotency_key) |

## Implications

```text
IN: payments table + RecordPayment + ListPayments + status rollup on student_fees
OUT: refunds, void payment, transactions, gateways, portal pay
```
