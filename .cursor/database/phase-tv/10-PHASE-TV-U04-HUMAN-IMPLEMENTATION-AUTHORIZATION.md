# PHASE TV — TV-U04
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Unit: TV-U04 — Create / Update / Cancel Schedule Application commands
Design Lock: 03 LOCKED
Date: 2026-09-12
Status: GRANTED
Authority: Absolute continuation (“استمر”)
```

## Intent

```text
Application CQRS for timetable.schedules:
  - CreateSchedule
  - UpdateSchedule (active only; fail-closed conflicts)
  - CancelSchedule (soft cancel; no hard delete)
Idempotency + outbox events
Tenant guards: section/teacher/room/period belong to school
No HTTP
No auto-generate
Exceptions commands can be deferred or thin add-on if time-boxed
```

## Human decision

```text
[x] GRANTED — proceed TV-U04
[ ] DENIED
[ ] DEFER
```
