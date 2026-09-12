# PHASE TV — TIMETABLE / VOCATIONAL
# DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 02 APPLIED
```

## Scope

### In

```text
- Harden timetable.periods (RLS/governance; no PK rewrite)
- Physicalize timetable.schedules (+ school_id) + FORCE RLS
- Physicalize timetable.schedule_exceptions (+ school_id) + FORCE RLS
- Application: Create / Update / Cancel schedule (idempotent; conflict fail-closed)
- Vocational Application: Create/Update/Deactivate specialization, track, subject links
- Vocational FORCE RLS hardening if missing
- PG integration tests + architecture fitness
```

### Out

```text
- Auto-generate / solver / AI timetable
- HTTP Timetable/Vocational writers (first units)
- periods.id → BIGINT rewrite
- Phase 7.6 read models
- Phase 8
- Workshops / ERP expansion
```

## Invariants

| ID | Rule |
|----|------|
| INV-TV-01 | Do not break `attendance.sessions.period_id` → `timetable.periods` |
| INV-TV-02 | All new TV tables: `school_id` + ENABLE/FORCE RLS |
| INV-TV-03 | No hard DELETE of schedule/vocational official rows — status/cancel + trigger reject |
| INV-TV-04 | Active schedule conflicts fail-closed (section/teacher/room × day × period × year) |
| INV-TV-05 | Schedules are operational capacity — not grade/result SSOT |
| INV-TV-06 | Vocational writes never invent parallel specialization SSOT outside `vocational.*` |
| INV-TV-07 | Blueprint object count stays **90** (physicalize existing sketches) |

## Physical intent — schedules (enrichment)

```text
timetable.schedules
  id BIGINT GENERATED ALWAYS AS IDENTITY
  school_id BIGINT NOT NULL
  section_id, academic_year_id, day_of_week, period_id
  subject_id, teacher_id, room_id NULL
  lifecycle_status SMALLINT, cancelled_at NULL
  correlation_id, created_by, timestamps
  UNIQUE active (section, year, day, period)
  UNIQUE active (teacher, year, day, period)
  UNIQUE active (room, year, day, period) WHERE room_id IS NOT NULL
```

## Unit plan

| Unit | Name | AuthZ |
|------|------|-------|
| **TV-U01** | periods harden (RLS audit/fix) | NEXT |
| TV-U02 | schedules schema + FORCE RLS | later |
| TV-U03 | schedule_exceptions schema + FORCE RLS | later |
| TV-U04 | Create/Update/Cancel Schedule commands | later |
| TV-U05 | vocational FORCE RLS harden | later |
| TV-U06 | Vocational Application commands | later |

```text
PHASE TV DESIGN LOCK: LOCKED
NEXT: TV-U01 Human Implementation Authorization Request
Implementation remains BLOCKED until unit AuthZ GRANTED
```
