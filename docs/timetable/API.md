# Timetable — API surface

All writes: authenticated, school from `SchoolContext` (`school_id` in the body is rejected), FormRequest
authorization through `TimetablePolicy`, header **`X-Idempotency-Key`** required, errors returned as translatable
codes (`errors.engine` for engine writes, flash `error` for domain refusals). Responses redirect back (Inertia).

## Web writes (`/timetable/...`, Inertia)

| Method · path | Ability (permission) | Command |
|---------------|----------------------|---------|
| POST `/settings` | manageConstraints (`timetable.constraints.manage`) | SaveTimetableSettings |
| POST `/activities` · PATCH `/activities/{id}` · POST `/activities/{id}/end` | manageConstraints | Create / Update / EndTimetableActivity |
| POST `/activities/sync` | manageConstraints | SyncTimetableActivities (from curriculum × assignments) |
| POST `/divisions` · POST `/divisions/{id}/end` | manageConstraints | SplitSectionIntoGroups / EndTimetableDivision |
| POST `/availability` | manageConstraints | SetTimetableAvailability |
| POST `/rules` · POST `/rules/{id}/end` | manageConstraints | SaveTimetableRule / EndTimetableRule |
| POST `/runs` | generate (`timetable.generate`) | QueueTimetableGeneration (→ queued job) |
| POST `/runs/{id}/cancel` · `/apply` · `/discard` | generate | Cancel / Apply / DiscardTimetableGeneration |
| POST `/schedules/lock` | lockSchedules (`timetable.schedule.update`) | LockSchedules |
| POST `/schedules/{id}/substitute` | createException (`timetable.exception.create`) | CreateScheduleException |
| POST `/versions` | publish (`timetable.publish`) | CreateTimetableVersion |
| POST `/versions/{id}/submit` · `/publish` · `/archive` · `/restore` | publish | Submit / Publish / Archive / RestoreTimetableVersion |
| POST `/versions/{id}/decide` | approve (`timetable.approve`) + workflow step role | DecideTimetableVersion → workflow decide |

Existing builder writes (schedules CRUD, swap, shift, auto-place, periods) are unchanged; locked lessons refuse
move / cancel (`timetable.schedule_locked`).

## Page props (`GET /timetable`)

Always: the builder props + `engine` (settings, activities, groups, availability, rules, rule catalogue, rooms,
workshops, recent runs, versions, status {published / effective version, stale}) + `viewingVersion` (with
`?version=ID`, read-only grid of that version).

On demand (`Inertia::optional`, partial reload): `runDetail` (`?run=ID`), `comparison` (`?compare_a=&compare_b=`,
empty = working grid), `moveSuggestions` (`?suggest=scheduleId`), `substitutes` (`?substitute=scheduleId&date=`).

Other pages: `GET /timetable/students/{student}` (student week), `GET /timetable/export?by=section|teacher[&version=]` (CSV, UTF-8 BOM).

## JSON API (`/api/v1/timetable/...`)

| Endpoint | Purpose |
|----------|---------|
| GET `effective?academic_year_id=&date=&section_id=&teacher_id=` | Lessons governing a date (published version or working grid, cycle week, substitutions applied) — for Attendance and portals |
| GET `students/{student}?academic_year_id=&date=` | A student's week from section + group membership |

Existing endpoints (`schedules`, `schedule-exceptions`, `periods`) unchanged.
