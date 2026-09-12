# PHASE 8.1 — QUALIFICATION VOID BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر» / «استم ر»)

---

```text
Date: 2026-09-13
Human: «استم ر» (= استمر) after WF-DECIDE-SYNC
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-81-VOID-001 | Open void now? | A yes · B hold | **A yes** |
| HD-81-VOID-002 | Mechanism | A hard delete · B status+effective_to | **B** |
| HD-81-VOID-003 | Status values | — | **1 Active · 2 Voided** |
| HD-81-VOID-004 | List behavior | A active only · B all with status | **B all with status** |
| HD-81-VOID-005 | Re-void | A idempotent success · B reject | **B reject (not active)** |
| HD-81-VOID-006 | Permission | A manageTeachers | **A manageTeachers** |
| HD-81-VOID-007 | Binary upload | A open · B HOLD | **B HOLD** |
| HD-81-VOID-008 | Body RLS | A add school_id · B HOLD | **B HOLD** |

## Implications

```text
IN: ALTER teacher_qualifications + VoidTeacherQualification HTTP + list status fields
OUT: hard delete; binary upload; RLS on body table
```
