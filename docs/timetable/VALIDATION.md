# Timetable — Validation, Workload & Quality (implemented)

**Implemented:** 2026-10-07 · **Schema change:** none · **New endpoint:** none (rides on `GET /timetable`).

Three pure Domain services computed on the `TimetableBoard` the workspace already loads. They are
read-only: nothing is stored, nothing is placed. The builder shows them in the «الجاهزية والجودة»
sheet (ribbon tab «تحرير»), next to «تدقيق الجدول».

| Service | Question | Output |
|---------|----------|--------|
| `TimetableAdvisor` | *Before placing:* can these lessons fit this week at all? | `verdict` ready / blocked, `findings`, `readiness` %, `totals` |
| `TeacherWorkloadAnalyzer` | How loaded is each teacher, and how does the grid treat them? | per teacher: required, placed, practical / theory, sections, per day, max day, gaps, longest run, capacity, status |
| `TimetableQualityScorer` | *After placing:* is the grid valid, complete, good? | `feasible`, `grade`, `overall`, `metrics`, `counts` |

The existing `TimetableAuditor` stays the verification of the grid itself (double bookings, lessons in
breaks, over / under placed, overloaded days, gaps, split practicals).

## Advisor findings

| Code | Severity | Rule |
|------|----------|------|
| `no_lesson_periods` | blocker | the school day has no lesson period |
| `section_overbooked` | blocker | Σ weekly lessons of a section > days × lesson periods |
| `teacher_overbooked` | blocker | Σ weekly lessons of a teacher > min(slots, days × 6) |
| `subject_over_daily_limit` | blocker | weekly lessons of a subject in a section > days × 2 |
| `practical_no_double_slot` | blocker | a practical with ≥ 2 weekly lessons, but no two lesson periods form a double |
| `teacher_inactive` | blocker | the assigned teacher is not active in the school this year |
| `teacher_not_qualified` | blocker | the subject is not in the teacher's `teacher_subjects` |
| `weekly_load_missing` | warning | the curriculum gives no weekly hours for the subject |
| `subject_split_between_teachers` | warning | one subject of a section is assigned to several teachers — each is counted for the full load until co-teaching is modelled |
| `section_without_lessons` | info | an active section has no teaching assignment (nor activity) |
| `activity_blocked` | blocker | a block can never be placed — detail: `workshop_capacity`, `room_capacity`, `no_room_of_type`, `workshop_unknown`, `activity_without_teacher` / `_targets` |
| `facility_overbooked` | blocker | a room / workshop is asked for more periods than it is open (time-off subtracted) |
| `teacher_time_off_overbooked` | blocker | a teacher's lessons exceed the slots left after their unavailability |

The last three come from compiling the engine problem (`ConstraintCompiler`), so they see activities, groups,
rooms, workshops and availability. Generation is refused while a blocker touches the run's scope
(`GenerationGate`), except in relaxed mode, and the blockers are shown with the refusal.
| `teacher_tight` | info | a teacher's load ≥ 90 % of the week |

**Readiness %:** school day (0 / 100), weekly loads known, teachers qualified, sections covered,
sections + teachers within capacity; `overall` = mean of the measured ones (`null` = nothing to
measure). Limits come from `SchoolWeek` (the same the auto-placer and audit use).

## Quality metrics

`completeness` (placed ÷ required, each requirement capped at its weekly load) ·
`teacher_compactness` / `section_compactness` (1 − free periods inside the day ÷ lessons) ·
`distribution` (section-subject-days within 2 a day) · `practical_doubles` (practical section-days in
one unbroken run) · `workload_balance` (teacher-days within 6). `grade`: `infeasible` when the audit has
errors, else `incomplete` while lessons are left, else `complete`. **A complete grid is not
automatically a good one** — the metrics say how good.

## Tests

`tests/Unit/Domain/TimetableAnalysisServicesTest.php` (ready / blocked cases, practical double slot,
empty day, workload, quality grades, day runs) and the workspace assertions in
`tests/Feature/Database/PostgreSql/TimetableBuilderPagePostgreSqlTest.php::auto_place_then_swap_shift_and_audit`.
