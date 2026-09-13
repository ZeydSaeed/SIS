# Phase Enrichment Show Wave-2 — Human Start Authorization

**Status:** OPEN  
**Branch:** `feature/phase-enrichment-show-wave2`  
**Predecessor:** Phase 10 HR-PAY AuthZ slice CLOSED WITH CONDITIONS (`feature/phase-hr-pay-slice1`) — payroll schema remains HOLD.

## Scope (Schema NONE)

Ten catalog GET show units where List (+ lifecycle) already exist:

1. COM-U12 messages show  
2. COM-U13 notification jobs show  
3. FIN-U19 finance transactions show  
4. TV-U23 workshop equipment show  
5. CUR-U13 curricula show  
6. CUR-U14 prerequisites show  
7. DOC-U05 documents metadata show  
8. ENR-U04 enrollment-subjects show  
9. PT-U05 promotion rules show  
10. AUDIT-U03 audit logs show  

## Absolute HOLDs (carry forward)

- No payroll tables / migrations  
- No `exam.session.cancel`  
- Ranking/PDF only after reopen 7.5/7.8  
- No bulk SMTP fan-out  
- section_batches / assignment enforce HOLD  
- No contracts / leaves / shifts schema  
