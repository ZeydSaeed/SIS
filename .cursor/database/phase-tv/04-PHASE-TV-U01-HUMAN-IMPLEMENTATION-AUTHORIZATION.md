# PHASE TV — TV-U01
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Unit: TV-U01 — timetable.periods harden (RLS audit / fix)
Design Lock: 03 LOCKED
Date: 2026-09-12
Status: GRANTED
Authority: Absolute continuation (“استمر”)
```

## Intent

```text
Audit live timetable.periods for tenant isolation (ENABLE + FORCE RLS + policy).
Add reject-hard-DELETE trigger if missing.
Do NOT rewrite periods.id (SMALLINT kept — HD-TV-004).
Do NOT break attendance.sessions.period_id FK/contract.
No schedule DDL in this unit (U02).
No HTTP.
```

## Allowed

- Migration(s) for RLS / delete-reject only on `timetable.periods`
- PG tests proving FORCE RLS + delete reject
- Blueprint note: periods RLS hardened (object count unchanged)
- Governance audit/closure artifact after PASS

## Forbidden

- schedules / schedule_exceptions DDL
- Vocational changes
- periods PK type migration
- Application schedule commands
- Phase 8

## Human decision

```text
[x] GRANTED — proceed TV-U01 implementation
[ ] DENIED — hold
[ ] DEFER — reason: ________
```
