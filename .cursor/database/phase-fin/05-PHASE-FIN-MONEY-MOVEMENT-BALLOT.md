# PHASE FIN — MONEY MOVEMENT BALLOT
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
| HD-FIN-001 | Next money table? | A student_fees · B payments · C transactions | **A student_fees** |
| HD-FIN-002 | school_id on student_fees? | A ADD for RLS · B derive only | **A ADD** |
| HD-FIN-003 | Amount source? | A snapshot fee_type · B always live join · C free-form only | **A snapshot** (+ optional override on assign) |
| HD-FIN-004 | Duplicate assign same enrollment+fee_type+year? | A allow · B UNIQUE reject | **B UNIQUE** |
| HD-FIN-005 | Payments / refunds / gateways this slice? | A open · B HOLD | **B HOLD** |
| HD-FIN-006 | transactions ledger this slice? | A open · B HOLD | **B HOLD** |
| HD-FIN-007 | student_fee status v1 (no payments)? | A Unpaid only · B Unpaid+Cancelled | **A Unpaid(1)** create; Cancel HTTP deferred |
| HD-FIN-008 | Hard delete student_fees? | A allow · B reject | **B reject** |

## Implications

```text
IN this slice: physicalize student_fees + AssignStudentFee + ListStudentFees
OUT: payments, transactions, refunds, portals, gateways, cancel/void HTTP
```
