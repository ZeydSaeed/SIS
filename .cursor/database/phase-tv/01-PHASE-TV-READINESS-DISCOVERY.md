# PHASE TV — TIMETABLE / VOCATIONAL
# READINESS DISCOVERY

---

```text
Date: 2026-09-12
Mode: READ-ONLY
Predecessor: Phase 7.5 CLOSED / ACCEPTED WITH CONDITIONS (16)
Program path: Timetable / Vocational (human-approved)
```

## Live inventory

| Object | Observed |
|--------|----------|
| `timetable.periods` | **LIVE** (created with attendance migration `100800`) |
| `timetable.schedules` | **ABSENT** |
| `timetable.schedule_exceptions` | **ABSENT** |
| `vocational.specializations` | **LIVE** (`100600`) |
| `vocational.tracks` | **LIVE** |
| `vocational.specialization_subjects` | **LIVE** |
| Domain/Application Timetable | **ABSENT** |
| Domain/Application Vocational | **ABSENT** |
| Admission/Enrollment → specialization_id | LIVE ID refs only |

## Attendance coupling

```text
attendance.sessions.period_id → timetable.periods (nullable, ON DELETE SET NULL)
CreateAttendanceSession validates periodBelongsToSchool()
⇒ periods are operational support TODAY — do not break FK/contract
```

## Prerequisite FKs for schedules (all LIVE)

| Dependency | Status |
|------------|--------|
| enrollment.sections | LIVE |
| academic.academic_years | LIVE |
| curriculum.subjects | LIVE |
| teachers.teachers | LIVE |
| organization.rooms | LIVE |
| timetable.periods | LIVE |

## Blueprint vs physical

| Schema | Blueprint | Physical | Gap |
|--------|-----------|----------|-----|
| timetable | 3 | 1 (`periods`) | schedules + exceptions |
| vocational | 3 | 3 | Application/CQRS surface only |

## Blueprint design notes / risks

```text
1. periods.id = SMALLINT in blueprint/migration — conflicts with modern BIGINT IDENTITY convention
2. schedules blueprint omits school_id — FORCE RLS needs school_id or join-enforced tenant path
3. Vocational tables may lack FORCE RLS (verify in hardening unit)
4. No conflict engine in blueprint (teacher/room double-booking) — product HD required
```

## Work packages (candidates post Lock)

| ID | Package |
|----|---------|
| WP-TV-01 | Harden `timetable.periods` (RLS/governance) without breaking attendance |
| WP-TV-02 | Physicalize `timetable.schedules` + FORCE RLS |
| WP-TV-03 | Physicalize `timetable.schedule_exceptions` + FORCE RLS |
| WP-TV-04 | Create/Update/Cancel schedule Application commands |
| WP-TV-05 | Vocational Application CRUD (specializations/tracks/subjects) on LIVE tables |
| WP-TV-06 | Vocational FORCE RLS hardening (if missing) |

## Absolute prohibitions

```text
- No Phase 8 open
- No auto-solver / AI timetable generation in v1
- No hard-delete of official academic schedule history without status/effective pattern (ballot)
- No breaking attendance.period_id contract
- No HTTP writers until unit AuthZ says otherwise
- No inventing workshop/ERP modules under this phase label
```
