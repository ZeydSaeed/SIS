# PHASE TV — PATH SELECTION + TV-U07 AUTHORIZATION

---

```text
Date: 2026-09-12
Human: “استمر ونفذ الافضل”
Selected: TV-U07 Schedule Exception Application commands
Rejected for now: Timetable HTTP · Student portal · Phase 8
Status: GRANTED
```

## Why this path

```text
- Completes Phase TV deferred gap (exceptions DDL existed; commands missing)
- Keeps no-HTTP / no-Phase-8 posture
- Lower risk than student portal privacy ballot
```

## Scope

```text
CreateScheduleException + UpdateScheduleException
Guards: schedule active in school; substitute teacher/room in school
Idempotency + outbox
No hard delete
No HTTP
```
