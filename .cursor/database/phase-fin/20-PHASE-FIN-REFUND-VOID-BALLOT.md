# PHASE FIN — REFUND / VOID PAYMENT BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» after COM mark-sent
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-FIN-REF-001 | Open refund/void now? | A yes · B hold | **A yes** |
| HD-FIN-REF-002 | Model | A silent UPDATE amount · B soft-void + ledger reverse | **B soft-void + append reverse** |
| HD-FIN-REF-003 | Partial refund amount? | A yes · B HOLD full void only | **B full void of payment row** |
| HD-FIN-REF-004 | Hard-delete payment? | A allow · B forbid | **B forbid** |
| HD-FIN-REF-005 | student_fees.status | A leave · B re-rollup from posted payments | **B re-rollup** |
| HD-FIN-REF-006 | Ledger type | — | **3 PaymentRefunded** (balance += amount) |
| HD-FIN-REF-007 | Cancel student_fee HTTP | A open · B HOLD | **B HOLD** |
| HD-FIN-REF-008 | Gateway / GL double-entry | A open · B HOLD | **B HOLD** |
| HD-FIN-REF-009 | Permission | A new · B manageFinance | **B manageFinance** |
| HD-FIN-REF-010 | Idempotency | A required · B none | **A X-Idempotency-Key** |

## Implications

```text
IN: payments.status (1 Posted / 2 Voided) + voided_at/voided_by
    VoidPayment HTTP + PaymentRefunded ledger line
OUT: partial refunds, cancel obligation HTTP, gateway, full GL
```
