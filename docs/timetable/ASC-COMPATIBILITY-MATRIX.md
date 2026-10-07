# aSc TimeTables — Compatibility Matrix

Source: the aSc TimeTables help (help.edupage.org → TimeTables: data input, groups & joins, bells,
days/weeks/terms, lessons grid, buildings, working with timetable, constraints, testing, generation,
verification, printing, supervisions, student-based timetable, export/import, sharing, online
administration, substitutions, cloud generator, AI module) read 2026-10-07. The PDF
`asc_timetables_en_P1.pdf` was not available on this machine; the online help was used instead.

**Legend:** ✅ exists · ◐ partial · ✗ missing · Phase = planned phase · – = not planned.

| aSc feature | SIS equivalent | Existing | Missing | Planned | Notes |
|---|---|---|---|---|---|
| Subjects | `curriculum.subjects` | ✅ | subject time-off, per-subject rooms | T2 | SIS owns subjects; timetable never re-enters them |
| Classes (grade + class) | `grade_levels`, `classes`, `sections` | ✅ | class time-off | T2 | aSc "class" ≈ SIS section |
| Classrooms | `organization.rooms` + activity room / room type | ✅ | assignment in builder, capacity check, shared room | T7/T9 | `schedules.room_id` exists, builder sends null |
| Teachers | `teachers.*` | ✅ | time-off, contracts as load targets | T2 | load = teaching assignments × curriculum hours |
| Lessons | `timetable.activities` (+ implicit from requirements) | ✅ | stored activity with count/length | T1 | `TimetableBoard::requirements` |
| Double / triple lessons | block length 1–6, distribution 2+2+1 | ✅ | any block length, 1+1+2 patterns | T1 | doubles only for `subject_type` 3 |
| Groups / divisions | divisions / division_groups / group_members | ✅ | divisions, groups, "same division simultaneous" rule | T1 | aSc rule adopted verbatim |
| Joined classes | activity with n targets, joined rows | ✅ | activity with n sections | T1 | `activity_sections` |
| Co-teaching (incl. 3 of 5) | activity_teachers.sessions, co_teacher_id | ✅ | `activity_teachers.sessions` | T1 | |
| Bells / breaks | `timetable.periods` + «توزيع الاستراحات» | ✅ | different bells per day / part of school | T1+ | bell sets; SMALLINT PK kept |
| Zero period, 6 lessons except Friday | – | ◐ | per-day period sets | T1+ | |
| Days (rename, 6-day, Fri–Sat weekend) | configs.working_days | ✅ | configurable working days | T1 | `configs.working_days` |
| Weeks (A/B, n-week cycle) | cycle_weeks + week_no | ✅ | `cycle_weeks`, `week_no` | T1 | |
| Terms | `academic.terms` | ◐ | activity bound to term | T1 | |
| Lessons grid | tray of lessons left per section | ◐ | full grid with counts per class × subject | T6 | |
| Buildings, transfers | – | ✗ | building on room, transfer breaks | – | not needed for single-site schools yet |
| Teacher constraints (gaps, days, max/day, consecutive, morning/afternoon, lunch) | rule catalogue + slot rules | ✅ | rule catalogue + priorities | T4 | |
| Subject constraints (max/day, distribution, same period, follow, not same day) | rule catalogue | ✅ | rule catalogue | T4 | |
| Class constraints (no gaps, start/end, shifts, groups finish together) | section rules (no gaps, min/max, end by) | ◐ | rule catalogue | T4 | |
| Classroom constraints (capacity, usage) | capacity hard, room-scoped slot rules | ◐ | rules + capacity hard check | T4/T9 | |
| Card relationships + "Apply to" | constraint_rules (scopes, precedence) | ✅ | `constraint_rules` with `apply_to` | T4 | explicit scope FKs, no polymorphism |
| Time-off (incl. per week) | availability (week_no) | ✅ | `availability` | T1/T2 | |
| Locked cards | schedules.locked_at | ✅ | `schedules.locked_at` | T7 | |
| Testing (pre-generation checks) | **`TimetableAdvisor`** | ✅ | extended with groups/rooms/time-off | T3 ✅ | READY / BLOCKED with reasons |
| Advisor (overbooked, more lessons than days, special rooms) | `TimetableAdvisor` | ◐ | room / time-off aware variants | T3+ | overbooked + daily-limit + double-slot done |
| Generation | HeuristicTimetableSolver, queued runs | ✅ | school-wide solver, queue, progress | T5 | |
| Complexity estimate | preview counts | ◐ | preview counts + estimate | T6 | |
| Relaxation (strict / relaxed) | modes + violation report | ✅ | modes + priority-based relaxation report | T5 | |
| Improve functions (never remove lessons) | optimize / repair modes | ✅ | Optimize-existing mode | T5 | |
| Verification | `TimetableAuditor` | ✅ | room / group / sequence checks | T4 | |
| Statistics | **`TimetableQualityScorer`, `TeacherWorkloadAnalyzer`** | ✅ | room / workshop utilisation | T9 | |
| Printing (class / teacher, A3, posters) | full-screen preview + A3 print | ◐ | teacher/room/student prints, A4, daily | T6+ | existing look preserved |
| Supervisions (duties) | – | ✗ | activity_type supervision | later | |
| Student timetable / seminars / course picks | student page + API from membership | ◐ | group members, electives | T10 | students never enter the solver core |
| Import / export (Excel, XML) | CSV export (Excel-ready) | ◐ | CSV/Excel export, read API | later | import not needed — SIS is the source |
| Online sharing / publish | versions + publish + effective API | ✅ | versions + publish + read model | T8 | |
| Versions, validity dates | timetable.versions | ✅ | `versions` with effective dates | T8 | aSc: new file + validity dates; SIS: version rows |
| Compare timetables | CompareTimetableVersions | ✅ | compare query | T6/T8 | |
| Cloud generator | – | – | queue workers play this role | T5 | no external service |
| Substitution | exceptions + SubstituteRanker | ✅ | suggestions, cancel/move/swap per date | T11 | |
| AI assistance | – | ✗ | NL → constraint proposal → confirm | T12 | aSc AI can change data; SIS requires confirmation |
