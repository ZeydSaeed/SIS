# Phase Operational UI — Human Start Authorization

**Status:** OPEN  
**Branch:** `feature/phase-operational-ui`  
**Predecessor:** `feature/phase-enrichment-wave6` (CLOSED @ `47bdea7`)  
**Bound by:** `.cursor/architecture/OPERATIONAL-TRANSITION-GATE.md` (G1–G8)

## Intent

Close **G7 Operational UI** (and supporting G6 Results UI) so daily staff/teacher/admin flows no longer require Postman.

Allowed: Inertia pages, thin page controllers → existing Application handlers, nav/hub links.  
Forbidden: open-ended catalog HTTP churn; HOLDs below; new ERP modules.

## Priority backlog

```text
[ ] Results / Transcript operational pages (G6+G7)
[ ] Enrollment create/update flows in UI
[ ] Attendance mark/close flows in UI
[ ] Exam / Grades operational pages
[ ] Core reports surfaces
```

## Absolute HOLDs (preserved)

- No payroll · no `exam.session.cancel` HTTP · Ranking/PDF HOLD · no bulk SMTP · section_batches HOLD · no contracts/leaves/shifts · no attendance CANCELLED→OPEN reopen · no Blazor
