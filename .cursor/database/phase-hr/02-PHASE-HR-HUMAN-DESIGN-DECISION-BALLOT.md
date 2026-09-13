# PHASE HR — HUMAN DESIGN DECISION BALLOT
# LOCKED UNDER: APPROVED — Phase HR / Phase 9

---

```text
Date: 2026-09-13
Human: APPROVED — Phase HR / Phase 9
Status: LOCKED — recommended defaults adopted for Slice-1
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-HR-001 | Open Phase HR now? | A yes · B hold | **A yes** (human APPROVED) |
| HD-HR-002 | First slice | A employees foundation · B payroll first · C leaves first | **A employees foundation** |
| HD-HR-003 | New schema | A `hr` · B reuse `teachers` · C `staff` | **A `hr`** |
| HD-HR-004 | Relationship to teachers | A merge/replace · B parallel + optional teacher_id · C ignore teachers | **B parallel + optional `teacher_id`** |
| HD-HR-005 | job_positions catalog | A in Slice-1 · B later | **A in Slice-1** |
| HD-HR-006 | employee_schools membership | A yes (like teacher_schools) · B school_id on employee only | **A employee_schools** |
| HD-HR-007 | Payroll / salary runs | A open · B HOLD separate AuthZ | **B HOLD** |
| HD-HR-008 | Contracts / leaves / shifts | A open · B HOLD | **B HOLD** |
| HD-HR-009 | `persons` SSOT rewrite | A open · B HOLD | **B HOLD** |
| HD-HR-010 | Hard delete employees | A allow · B reject | **B reject** (status + effective dates) |
| HD-HR-011 | PK strategy | A BIGINT IDENTITY · B UUID | **A BIGINT IDENTITY** (ADR-020) |
| HD-HR-012 | Permissions | A hr.view/hr.manage · B reuse teachers.* | **A hr.view / hr.manage** |

## Implications

```text
IN (Slice-1 design):
  hr.job_positions
  hr.employees (+ optional teacher_id, user_id)
  hr.employee_schools (school_id + academic_year_id + is_primary)
  FORCE RLS · Create/List HTTP · no payroll

OUT:
  payroll_* · contracts · leaves · shifts · persons table · teacher merge
```
