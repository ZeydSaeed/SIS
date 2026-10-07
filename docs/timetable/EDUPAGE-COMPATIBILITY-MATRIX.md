# EduPage — Compatibility Matrix (timetable-related)

Source: help.edupage.org sections *TimeTables – Sharing*, *Timetables online – Administration*,
*Substitutions / cover* (basic information, input, somebody/nobody missing, publish, notifications,
teacher view, mobile app), *Cloud generator*, *Mobile application*, *AI modul*, read 2026-10-07.
EduPage is a full school platform; only the parts that touch the timetable are classified. SIS already
owns most of the surrounding modules — they are **integrated, not rebuilt**.

**Legend:** ✅ exists · ◐ partial · ✗ missing.

| Area | EduPage capability | SIS today | Gap | Phase | Decision |
|---|---|---|---|---|---|
| Core timetable | edit timetable online, several colleagues | web builder (drag & drop, audit, auto-place) | concurrent-edit protection | T7 | optimistic version check on writes |
| Core timetable | saved versions, reopen previous | ✅ | versions + history | T8 | |
| Online administration | publish new timetable with validity dates during the year | ✅ | publish with `effective_from/to`, supersede | T8 | |
| Online administration | which timetable is valid on a date | ✅ | effective-version query | T8 | |
| Online administration | delegate timetable administrators | ✅ permissions + roles | finer permissions (generate/approve/publish) | T8 | decision D3 |
| Online administration | week A/B start, term dates | ◐ terms exist | cycle anchor date | T1 | |
| Publishing | public / students / parents see timetable | ◐ student page + API | read model per student / section / teacher | T8/T10 | through SIS auth only — no public page by default |
| Publishing | restrict who sees other timetables | ✅ policy + RLS | per-role visibility of other sections | T8 | |
| Sharing | send timetable to teachers, digital screen, calendar (ICS) | ✗ | exports (PDF/CSV/ICS) | later | |
| Substitutions | input absent teachers / classes / rooms | ✗ | absence input (reuse HR/attendance absence if present) | T11 | do not create a duplicate absence domain |
| Substitutions | generate substitutions for a day, ranking | ✅ | Domain ranking (qualified, free, load) | T11 | |
| Substitutions | move / replace / swap / cancel a lesson on a date | ◐ `schedule_exceptions` (substitute teacher / room) | cancel, move, swap per date | T11 | extend exceptions, additive |
| Substitutions | long-time absence, multi-teacher lessons | ✗ | range exceptions, per-teacher cancel | T11 | |
| Substitutions | publish daily changes, notify, confirm | ✗ | events → `communication` outbox | T11 | existing notification queue |
| Teachers | teacher's own timetable and daily substitutions | ◐ teacher view in builder | teacher self-service read | T8 | |
| Students | student timetable incl. groups / electives | ◐ groups ✅, electives ✗ | group membership read model | T10 | |
| Parents | child's timetable | ✗ | via guardians → student read model | T10 | |
| Calendar | holidays / events affecting days | ◐ `academic.holidays` | week-specific overrides | T11 | |
| Attendance | lesson attendance from the timetable | ◐ `attendance.sessions.period_id` | session prefill from effective timetable | T11 | Attendance stays owner |
| Integration | cloud generator, progress on mobile | ✅ queued generation + live progress | queued generation + progress in UI | T5/T6 | |
| AI | AI panel creating / editing lessons | ✗ | proposals only, human confirms | T12 | |
