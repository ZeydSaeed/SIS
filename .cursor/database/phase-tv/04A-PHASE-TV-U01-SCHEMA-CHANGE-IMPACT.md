# PHASE TV — TV-U01
# SCHEMA CHANGE IMPACT

---

```text
Change: Harden timetable.periods (FORCE RLS + reject DELETE)
Class: Medium (RLS policy + trigger)
Risk: LOW–MEDIUM
PK rewrite: NONE (HD-TV-004)
Attendance FK: UNCHANGED
Blueprint count: UNCHANGED (90)
```

## Checklist

- [x] Table in blueprint (`timetable.periods`)
- [x] No DROP column/table
- [x] No PK/type change
- [x] FORCE RLS + school isolation policy
- [x] Hard DELETE rejected (operational catalog still non-deletable)
- [x] Rollback path in migration `down()`
- [x] PG tests for RLS + delete reject + SMALLINT PK

## Blast radius

| Consumer | Impact |
|----------|--------|
| attendance.sessions.period_id | None (FK intact; SET NULL still defined but DELETE blocked) |
| CreateAttendanceSession periodBelongsToSchool | Must set `app.current_school_id` (already required pattern) |
| Timetable schedules (U02) | Will FK to periods; RLS parent ready |
