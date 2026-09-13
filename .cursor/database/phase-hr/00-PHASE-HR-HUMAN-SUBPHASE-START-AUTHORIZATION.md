# PHASE HR (MASTER PHASE 9)
# HUMAN SUBPHASE START AUTHORIZATION

---

```text
Document Type: HUMAN MASTER PHASE START AUTHORIZATION
Master Phase: PHASE HR / PHASE 9
Date: 2026-09-13
Human: APPROVED — Phase HR / Phase 9
Status: GRANTED — START + DESIGN TRACK
Implementation units: require separate unit AuthZ (U01+)
```

## Domain selected

```text
[x] Phase HR — Staff / Employees foundation (Educational ERP HR)
[ ] Full Payroll / salary runs — FORBIDDEN until separate Payroll AuthZ
[ ] Leaves / shifts / substitutions — DEFERRED after employees foundation
[ ] Rewrite teachers.* into hr.* — FORBIDDEN in v1 (bridge optional later)
```

## Why now

```text
- Phase 8 Teachers CLOSED WITH CONDITIONS (staff teaching path live)
- Master Prompt Phase 9 = Staff/HR — human explicitly APPROVED
- Live DB has NO hr schema (discovered 2026-09-13)
- teachers.* remain SSOT for teaching assignments; HR is generalized staff
```

## Grant meaning

```text
AUTHORIZED by this stamp:
  - phase-hr governance folder
  - readiness discovery
  - design ballot + Design Lock for Slice-1
  - unit AuthZ chain (U01…)

NOT authorized by this stamp alone:
  - CREATE TABLE / migrations
  - HTTP / permissions seed
  - Payroll, contracts money, GL payroll posting
```

## Absolute holds (still apply)

```text
- No exam.session.cancel
- No DEFAULT student_grades partition
- No silent mutate of official ledgers
- No DROP of teachers.* / merge-destroy without destructive AuthZ
- No invent Ranking/PDF without reopening 7.5/7.8
```
