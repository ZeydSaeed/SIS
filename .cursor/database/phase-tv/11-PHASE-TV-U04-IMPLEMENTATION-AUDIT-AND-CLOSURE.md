# PHASE TV — TV-U04
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: TV-U04 — Create / Update / Cancel Schedule commands
AuthZ: 10 GRANTED (استمر)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

- Domain: `App\Domain\Timetable\**` (VO, exceptions, events, repository port, idempotency)
- Application: CreateSchedule / UpdateSchedule / CancelSchedule (+ Results)
- Infrastructure: `EloquentScheduleRepository` (tenant guards, RLS set_config)
- DI binding in `ArchitectureServiceProvider`
- PG: `PhaseTvScheduleCommandsPostgreSqlTest` (2 PASS)
- `architecture:validate --fitness` PASS

## Guards enforced

- section ∈ school+year via classes
- period ∈ school
- teacher ∈ teacher_schools for school+year
- room ∈ school via branch (nullable)
- soft cancel only (lifecycle 2 + cancelled_at)

```text
TV-U04: CLOSED / ACCEPTED
NEXT: TV-U05 vocational FORCE RLS harden
```
