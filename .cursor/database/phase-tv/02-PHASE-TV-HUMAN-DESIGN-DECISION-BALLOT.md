# PHASE TV — TIMETABLE / VOCATIONAL
# HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Explicit human approval of Timetable/Vocational path
          + agent RECOMMENDED SET APPLIED (absolute continuation pattern)
Implementation: NOT AUTHORIZED until Design Lock + unit AuthZ
```

---

### HD-TV-001 — Subphase scope

```text
[x] A — Complete Timetable scheduling (schedules + exceptions + commands)
        AND Vocational Application surface on existing DDL (staged units)
[ ] B — Timetable only
[ ] C — Vocational Application only
[ ] D — Defer entire TV
```

---

### HD-TV-002 — Unit sequence

```text
[x] A — Timetable first (gap = 2 missing tables + ops value), then Vocational CQRS
        U01 periods harden → U02 schedules DDL → U03 exceptions DDL
        → U04 schedule commands → U05 vocational RLS harden → U06 vocational commands
[ ] B — Vocational first (DDL already live), then Timetable
[ ] C — Parallel mixed without order
```

**Rationale:** Attendance already depends on periods; schedules are the largest operational gap.

---

### HD-TV-003 — Tenant column on schedules

```text
[x] A — Add school_id NOT NULL on schedules + exceptions (FORCE RLS native)
        Composite FKs / guards to section/teacher/room/period belonging to school
[ ] B — Rely on join-through section only (no school_id) — REJECT for FORCE RLS clarity
```

---

### HD-TV-004 — periods PK modernization

```text
[x] B — KEEP existing SMALLINT identity of periods in v1 (do not rewrite PK)
        Document TECHNICAL DEBT; avoid breaking attendance.period_id
[ ] A — Migrate periods.id → BIGINT GENERATED ALWAYS AS IDENTITY now
```

---

### HD-TV-005 — Schedule lifecycle / delete

```text
[x] A — Soft lifecycle: status SMALLINT + cancelled_at / effective pattern;
        reject hard DELETE via trigger (align SIS academic non-delete rule)
[ ] B — Hard delete allowed for draft schedules only
[ ] C — Hard delete always — FORBIDDEN
```

---

### HD-TV-006 — Conflict detection (teacher / room / section)

```text
[x] A — Fail-closed uniqueness:
        - UNIQUE(section_id, academic_year_id, day_of_week, period_id) active rows
        - UNIQUE(teacher_id, academic_year_id, day_of_week, period_id) active rows
        - UNIQUE(room_id, academic_year_id, day_of_week, period_id) where room NOT NULL, active
        Application validates before insert; DB unique as last line
[ ] B — Soft warn only (allow double-booking)
[ ] C — Defer all conflict rules
```

---

### HD-TV-007 — Auto-generate timetable

```text
[x] B — DEFER solver / generator — manual CreateSchedule only in TV v1
[ ] A — Build generator in this phase
```

---

### HD-TV-008 — schedule_exceptions scope

```text
[x] A — Include exceptions table in TV (substitute teacher/room by date)
[ ] B — Defer exceptions to later subphase
```

---

### HD-TV-009 — Vocational v1 commands

```text
[x] A — Create/Update/Deactivate Specialization + Track + link SpecializationSubject
        (no hard delete; status deactivate)
[ ] B — Read-only recognition of existing tables only
```

---

### HD-TV-010 — HTTP

```text
[x] B — No HTTP Timetable/Vocational writers in first units
        Application commands + PG tests only until later AuthZ
[ ] A — HTTP CRUD in first units
```

---

### HD-TV-011 — Blueprint count

```text
[x] A — schedules + schedule_exceptions already in blueprint (90 objects)
        Physicalizing them does NOT increase blueprint object count
        school_id on schedules = schema enrichment of existing objects
[ ] B — Treat schedules as new blueprint objects (would double-count) — REJECT
```

---

### HD-TV-012 — Phase 8

```text
[x] A — Phase 8 remains NOT OPENED
[ ] B — Open Phase 8 in parallel — FORBIDDEN by Master Phase 7 gate
```

---

## RECOMMENDED SET (APPLIED)

| ID | Choice |
|----|--------|
| 001 | A — Timetable + Vocational staged |
| 002 | A — Timetable first |
| 003 | A — school_id on schedules/exceptions |
| 004 | B — keep periods SMALLINT PK |
| 005 | A — soft lifecycle + reject DELETE |
| 006 | A — fail-closed conflict uniques |
| 007 | B — no auto-generate |
| 008 | A — include exceptions |
| 009 | A — vocational write commands |
| 010 | B — no HTTP first units |
| 011 | A — no blueprint count inflation |
| 012 | A — Phase 8 hold |

```text
BALLOT STATUS: RECORDED / APPLIED
Continue → Design Lock
```
