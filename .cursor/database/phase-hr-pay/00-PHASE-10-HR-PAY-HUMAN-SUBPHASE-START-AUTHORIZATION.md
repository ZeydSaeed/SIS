# Phase 10 / HR-PAY Slice-1 — Human Start Authorization

**Status:** AuthZ slice CLOSED WITH CONDITIONS · schema ballot still required  
**Branch:** `feature/phase-hr-pay-slice1`  
**Predecessor:** enrichment wave on `feature/phase-hr-com-doc-fin-tv-closures` (20-step soft reactivate + catalog GET shows) CLOSED for this wave.

## Absolute HOLDs (carry forward)

- No payroll tables / migrations without new ballot + `database-change` skill
- No `exam.session.cancel`
- Ranking/PDF only after reopen 7.5/7.8
- No bulk SMTP fan-out
- section_batches / assignment enforce HOLD

## Slice-1 intent (AuthZ / design only until ballot)

1. Document payroll domain boundaries vs HR Slice-1 (employees / positions) — already CLOSED WITH CONDITIONS
2. Draft AuthZ permission catalog candidates: `hr.payroll.view`, `hr.payroll.manage` — **DELIVERED** (U01–U05)
3. No DEFAULT partition work; Schema ALTER = **NONE** in AuthZ slice

## Next gate

Human ballot must approve before any `hr.payroll_*` (or equivalent) table creation.
