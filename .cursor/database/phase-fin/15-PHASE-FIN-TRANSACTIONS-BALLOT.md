# PHASE FIN — TRANSACTIONS LEDGER BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر» / «استم ر»)

---

```text
Date: 2026-09-12
Human: «استمر» (typo: استم ر)
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-FIN-TXN-001 | Open transactions now? | A yes · B hold | **A yes** |
| HD-FIN-TXN-002 | school_id? | A ADD · B derive | **A ADD** |
| HD-FIN-TXN-003 | Partition year-1? | A yes · B no until measured | **B no** (adaptive governance) |
| HD-FIN-TXN-004 | Write path? | A public HTTP create · B append from fee/payment only | **B append only** |
| HD-FIN-TXN-005 | Types v1 | — | **1 FeeAssigned · 2 PaymentReceived** |
| HD-FIN-TXN-006 | balance_after | A null · B running outstanding (charges−payments) | **B running** |
| HD-FIN-TXN-007 | Refunds/adjustments | A open · B HOLD | **B HOLD** |
| HD-FIN-TXN-008 | Hard delete | A allow · B reject | **B reject** |

## Implications

```text
IN: physicalize transactions + List HTTP + append hooks on AssignStudentFee + RecordPayment
OUT: partition, refunds, manual create, adjustments
```
