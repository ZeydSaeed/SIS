# SIS Operational Transition Gate

**Version:** 1.0  
**Status:** ACTIVE — enrichment must converge on this gate  
**Date:** 2026-09-14  
**Authority:** Human-approved operational end-line (enrichment is not open-ended)

---

## Purpose

Define a clear **stop line** for Build / Enrichment so work does not continue Wave N forever.

```text
BUILD / ENRICHMENT
        ↓
OPERATIONAL BASELINE (this gate: G1–G8)
        ↓
🛑 STOP FEATURE EXPANSION (open-ended enrichment)
        ↓
OPERATIONALIZATION (Load / DR / Monitoring / Backup)
        ↓
PRODUCTION GATE
        ↓
GO LIVE
```

When **G1–G8 = PASS** → **STOP ENRICHMENT** → enter Operational Phase.

---

## What “done enough” means

> All capabilities required for **basic daily SIS operation** exist, are tested, and are governance-closed — no functional gap that blocks core daily use.

**Not required before stop:** Payroll · Inventory · Medical · Transport · Library · Advanced Finance · Advanced HR · Blazor Desktop · full AI / Self-Healing · advanced Ranking/PDF (HOLD).

Those are **Post-Go-Live Expansion**.

---

## Gates G1–G8

| Gate | Required | Approx status | Notes |
|------|----------|---------------|-------|
| **G1 — Core SIS** | Schools, years/terms, students, guardians, enrollment | 🟢 Advanced | Catalog HTTP expanding; UI thin |
| **G2 — Academic Ops** | Curriculum, teachers, classes/sections, attendance | 🟢 Advanced | API strong; UI thin |
| **G3 — Exams & Grades** | Exam/session/enrollment, grades, correct/finalize | 🟢 Strong | No `exam.session.cancel` HTTP (HOLD) |
| **G4 — Lifecycle** | Admission, promotion, transfers, graduation, certificates | 🟢/🟡 | Graduation HTTP / HD holds may remain |
| **G5 — Scheduling** | Period, schedule, room, teacher/section binding + basic conflict | 🟡 | API largely present; conflict/UI TBD |
| **G6 — Results** | Term/annual/GPA/transcript minimum (not only raw grades) | 🟡 | Read APIs exist; operational UI missing |
| **G7 — Operational UI** | Staff/teacher/admin daily flows via Inertia | 🔴 | Largest gap (~students pages only) |
| **G8 — Release Readiness** | Regression, security/RLS, backup/restore, performance baseline | 🔴 | See `production-readiness.md` |

---

## Absolute HOLDs (never reopen casually)

```text
Payroll / contracts / leaves / shifts without ballot
exam.session.cancel HTTP
Advanced Ranking / PDF until reopen 7.5/7.8
Bulk SMTP fan-out
section_batches / assignment HOLD
Attendance CANCELLED→OPEN reopen
```

---

## Enrichment policy under this gate

1. **Allowed enrichment** only if it closes a G1–G8 gap (especially G5–G7).  
2. **Forbidden:** open-ended catalog churn that does not move a gate toward PASS.  
3. **UI work is mandatory** for G7 — API-only is not Operational Baseline.  
4. After G1–G8 PASS: no new Waves for expansion features; open **Operationalization** track instead.

---

## Closure checklist (all must be PASS)

```text
[ ] G1 Core SIS
[ ] G2 Academic Operations
[ ] G3 Exams/Grades
[ ] G4 Lifecycle (required flows)
[ ] G5 Basic Scheduling
[ ] G6 Basic Results/Transcript
[ ] G7 Operational UI
[ ] G8 Release Readiness (tests + security + backup + perf baseline)
```

```text
╔══════════════════════════════════════════════╗
║     OPERATIONAL TRANSITION GATE              ║
╠══════════════════════════════════════════════╣
║ RESULT when all PASS:                        ║
║ 🛑 STOP ENRICHMENT                            ║
║ → ENTER OPERATIONAL PHASE                    ║
╚══════════════════════════════════════════════╝
```

---

## Related files

| File | Role |
|------|------|
| `.cursor/brain/PROJECT.md` | Project identity / maturity scores |
| `.cursor/architecture/production-readiness.md` | Production go-live (after operational baseline) |
| `.cursor/architecture/WORK-PLAN.md` | Phase A–F guide (docs; may lag live code) |
| `.cursor/database/` | Unit ballots / closure gates (implementation SSOT) |
| This file | **Enrichment stop-line SSOT** |
