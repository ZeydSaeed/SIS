# PHASE 8 — TEACHERS BODY RLS BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» after 8.1-VOID
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-8-RLS-001 | Open body RLS now? | A yes · B hold | **A yes** |
| HD-8-RLS-002 | Tables | A teachers+qualifications · B teachers only · C quals only | **A both** |
| HD-8-RLS-003 | Isolation model | A add school_id column · B EXISTS teacher_schools | **B membership EXISTS** |
| HD-8-RLS-004 | teachers INSERT WITH CHECK | A require membership · B require school setting only | **B setting only** (register before assignSchool) |
| HD-8-RLS-005 | quals INSERT/UPDATE | A membership EXISTS · B setting only | **A membership** |
| HD-8-RLS-006 | Binary upload / rename / multi-school | A open · B HOLD | **B HOLD** |

## Implications

```text
IN: FORCE RLS policies on teachers.teachers + teacher_qualifications
OUT: adding school_id columns; binary upload; employee_code rename
```
