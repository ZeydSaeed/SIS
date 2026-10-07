# Timetable — Discovery & Gap Analysis (Phase T0)

**Date:** 2026-10-07 · **Branch:** `V2` · **Scope:** the existing Timetable bounded context vs the
"Master Timetable & Scheduling Engine" specification.
**Status:** T0 discovery (this document) → decisions D1–D5 approved → **T1–T11 built 2026-10-07**. Sections A–O
describe the state *before* the build; §P / §R carry the outcome.

---

## A. Existing implementation

The Timetable context is real and layered (Clean Architecture, RLS, no hard delete). It is a
**section-grid builder**, not yet a scheduling engine.

| Layer | What exists |
|-------|-------------|
| Domain `app/Domain/Timetable` | `TimetableBoard` (pure in-memory model of one school-year grid) · `TimetableAutoPlacer` (deterministic greedy placer for one section) · `TimetableAuditor` (error / warning / info issues) · `ScheduleShiftPlanner` («زحف») · `SchoolDayPlanner` (lays out breaks) · `PeriodTimeGuard` · `SchoolWeek` (constants) · lifecycle VOs · events · repository ports |
| **New (this phase)** | `TimetableAdvisor` (READY / BLOCKED + readiness %) · `TeacherWorkloadAnalyzer` · `TimetableQualityScorer` · `Support/DayRuns` — see [VALIDATION.md](./VALIDATION.md) |
| Application | Commands: Create/Update/Cancel/Reactivate Schedule, Swap, Shift, AutoPlaceSection, Create/Update Period, ArrangeSchoolDay, Create/Update ScheduleException. Queries: workspace, schedule, periods, exceptions. `TimetableBoardLoader` |
| Infrastructure | Eloquent repositories for periods, schedules, exceptions; `EloquentTimetableWorkspaceReadRepository` (lessons from `teachers.teaching_assignments` × curriculum weekly hours) |
| HTTP | Web `TimetablePageController` (`/timetable/*`, Inertia) · API `ScheduleController`, `PeriodController` (`/api/v1/timetable/*`) · FormRequests · `TimetablePolicy` + `TimetableSchoolAccessService` |
| Security | Permissions `timetable.view`, `timetable.schedule.create/update/cancel`, `timetable.exception.create/update`; FORCE RLS on all three tables; reject-DELETE triggers; idempotency keys; security audit `TimetableDataAccess` |
| Tests | `TimetableBuilderServicesTest`, `TimetableAnalysisServicesTest` (unit) · `TimetableBuilderPagePostgreSqlTest`, `PhaseUiTtTimetablePagePostgreSqlTest` + Schedule/Period API suites (PostgreSQL) |

## B. Missing implementation (vs spec)

Activity model (groups, joins, co-teaching, durations, frequency patterns) · availability / time-off ·
configurable constraints with priorities and scopes · card relationships · solver interface and
school-wide generation · generation runs, snapshots, queue, progress, cancellation · relaxation and
infeasibility diagnosis · versioning / approval / publication · stale detection · rooms and
workshops in placement · student timetable · substitution engine and effective daily timetable ·
compare versions · what-if simulation · reports / exports beyond the A3 print.

## C. Existing database support (reused — never duplicated)

| Need | Source of truth |
|------|-----------------|
| School / year / term / holidays | `organization.schools`, `academic.academic_years`, `academic.terms`, `academic.holidays` |
| Branch → department → specialization | `organization.branches`, `organization.departments`, `vocational.specializations`, `vocational.tracks` |
| Grade / class / section, homeroom teacher | `academic.grade_levels`, `enrollment.classes`, `enrollment.sections` (`homeroom_teacher_id`) |
| Students per section | `enrollment.enrollments` (active) — the builder already reads per-section counts |
| Subjects, practical flag | `curriculum.subjects` (`subject_type` 3 = عملي) |
| Weekly load | `curriculum.curriculum_subjects.weekly_hours` (department curriculum, else general) |
| Prerequisites (for theory→practice) | `curriculum.prerequisites` |
| Who teaches what, where | `teachers.teaching_assignments` (branch / department / class / section) + `teachers.teacher_subjects` |
| Qualifications | `teachers.teacher_qualifications` |
| Rooms | `organization.rooms` (`capacity`, `room_type`) |
| Workshops / equipment | `vocational.workshops` (`capacity`, `safety_capacity`, `room_id`), `vocational.workshop_equipment` (`quantity`) |
| School day | `timetable.periods` (lesson / break, SMALLINT PK — HD-TV-004) |
| Grid | `timetable.schedules` (section × day × period, teacher, optional room; partial uniques per slot) |
| Date overrides | `timetable.schedule_exceptions` (substitute teacher / room per date) |
| Attendance link | `attendance.sessions.period_id` → `timetable.periods` |

## D. Missing database support (proposal only — gate)

See [TIMETABLE-DOMAIN-SPECIFICATION.md §6](./TIMETABLE-DOMAIN-SPECIFICATION.md#6-proposed-schema-gate).
Summary: `timetable.configs`, `activities` (+ `activity_sections`, `activity_teachers`),
`divisions` / `division_groups` (+ later `group_members`), `availability`, `constraint_rules`,
`versions` (+ `version_entries`), `generation_runs`; additive `locked_at/locked_by` on `schedules`.
No existing table is altered destructively; no PK changes.

## E. Existing UI — CURRENT TIMETABLE UI INVENTORY

`resources/js/pages/timetable/index.tsx` (≈1 700 lines) inside the app's window shell
(`AppLayout`, `window-registry.ts`, page ribbon). All props come from Inertia; no client fetch.

| Question | Answer |
|----------|--------|
| Where does the timetable start? | `TimetableIndex` → `TimetablePage`, route `GET /timetable` (`require.school.context`) |
| Main component | `TimetablePage`; sheets `LessonSheet`, `AuditSheet`, `PeriodsSheet`, **`ReadinessSheet` (new)** |
| Structure | **Days → Periods → Cards** per section; rows = sections (grouped branch › department in «عرض الكل»); columns = Sunday–Thursday × lesson periods, breaks shown with tone by length |
| Card | `sis-timetable-card`: subject (colour from subject index), teacher short name; practical marker; tray cards for lessons left to place |
| Day / period selection | fixed `DAYS = [1..5]`, periods from `timetable.periods` sorted by number; 12-hour clock (ص/م) |
| Opening a lesson | click → `LessonSheet` (change subject / teacher, shift ±, delete) |
| Drag & drop | yes — native HTML5 DnD: tray → cell (create), cell → cell (move), card → card (swap, confirm dialog); busy-teacher cells marked live |
| Filters | year, branch, department, class, section (A/B/C SSOT); teacher picker in teacher view; «عرض الكل» |
| Views | by section, by teacher |
| Responsive | desktop-first grid with horizontal scroll; full-screen preview; A3 landscape print layout (6 tables / sheet) |
| Window manager | yes — existing ribbon tabs «الصفحة الرئيسية» / «تحرير» / «إضافة» via `useRegisterPageRibbon`; sheets via `RegistrySheetDialog` |
| Audit | «تدقيق الجدول» sheet with counts on the ribbon, «انتقال» jumps to the cell and highlights lessons |
| **Added now** | «الجاهزية والجودة» ribbon command (tab «تحرير») → readiness verdict + %, blockers/warnings, quality metrics, teacher workload table. Reuses existing classes only — no new CSS, colours or layout |

## F. Missing UI

Room / subject / student / branch / master views · room and workshop assignment on cards ·
generate dialog (scope, mode, preserve, objectives) with preview, progress, results, compare ·
conflict side-panel with suggested actions · card context menu · lock / unlock · undo / redo ·
constraint editor · activity editor (groups, joins, co-teachers, durations) · availability editor ·
version list / publish / approve · substitution day view · compact / normal / detailed card modes.

## G. Existing APIs

Web (Inertia): `GET /timetable`, `GET /timetable/{schedule}`, `POST /timetable/periods`,
`POST /timetable/periods/arrange`, `PATCH /timetable/periods/{id}`, `POST/PATCH /timetable/schedules[/{id}]`,
`POST /timetable/schedules/{id}/cancel|swap|shift`, `POST /timetable/schedules/auto-place`.
API v1: schedules CRUD + cancel/reactivate, schedule exceptions, periods (read).

## H. Missing APIs (planned, no duplicates)

`POST /timetable/generation-runs` (+ `GET …/{id}` status, `POST …/{id}/cancel`, `POST …/{id}/apply`),
`POST /timetable/versions/{id}/approve|publish`, `GET /timetable/versions/compare`,
`POST /timetable/schedules/{id}/lock|unlock`, activity and constraint CRUD,
`GET /timetable/students/{id}` (read model), substitution suggestions. Validate / statistics / workload
need **no new endpoint**: they ride on the existing `GET /timetable` workspace (done now).

## I. Existing timetable engine

`TimetableAutoPlacer`: one section at a time, greedy cell-by-cell packing, hard rules = section and
teacher free, teacher ≤ 6/day, subject ≤ 2/day, practical in adjacent doubles; then hole compaction.
Deterministic. No rooms, no availability, no school-wide search, no backtracking, no scoring.

## J. Required solver

A replaceable `TimetableSolver` port fed by a constraint compiler, a school-wide heuristic
(construct + local repair) as the first adapter, queued generation runs with snapshots, relaxation by
priority, and explainable failure. See [TIMETABLE-SOLVER-SPECIFICATION.md](./TIMETABLE-SOLVER-SPECIFICATION.md).

## K. aSc compatibility — see [ASC-COMPATIBILITY-MATRIX.md](./ASC-COMPATIBILITY-MATRIX.md)

## L. EduPage compatibility — see [EDUPAGE-COMPATIBILITY-MATRIX.md](./EDUPAGE-COMPATIBILITY-MATRIX.md)

## M. Vocational requirements — see [VOCATIONAL-TIMETABLE-SPECIFICATION.md](./VOCATIONAL-TIMETABLE-SPECIFICATION.md)

## N. Performance concerns

- The workspace loads one school-year (all sections) per request; the new analysers are O(lessons)
  in memory on the board already loaded — no extra query except the existing sections read reused.
- School-wide generation must leave HTTP (queue) once it covers more than one section.
- Never load students into the solver; groups carry counts. Student timetables are a read model.
- Indexes for new tables only from the query patterns in the domain spec (no speculative indexes).

## O. Security / RLS concerns

- Every new table: `school_id NOT NULL`, FORCE RLS on `app.current_school_id`, reject-DELETE trigger.
- Solver runs inside a queued job → the job must re-bind school context (`set_config`) before reads.
- Publish / approve / generate need **new permissions** (`timetable.generate`, `timetable.approve`,
  `timetable.publish`, `timetable.constraints.manage`) — a security-catalogue change (gate).
- The readiness sheet adds no write path; it is visible to `timetable.view` holders like the audit.

## P. Proposed phases

| Phase | Content | Status |
|-------|---------|--------|
| T0 | Discovery, matrices, specs | Done |
| T1 | Domain model: activities, groups, availability, rules, versions, runs (3 migrations) | **Done** |
| T2 | Configuration UI: settings, activities, groups, availability | **Done** |
| T3 | Pre-generation validator (advisor, engine-aware) | **Done** |
| T4 | Constraint engine: catalogue, priorities, precedence, origin | **Done** |
| T5 | Solver port + PHP heuristic + queued runs, snapshots, progress, cancel | **Done** |
| T6 | Generate dialog, preview, progress, review, compare | **Done** |
| T7 | Locks, intelligent swap («اقتراح أماكن»), repair mode, what-if | **Done** |
| T8 | Versions, workflow approval, publish with dates, history, restore, stale detection | **Done** |
| T9 | Workshops (capacity, exclusivity, time windows), splits, theory → practice | **Done** (templates, equipment units: not built — vocational spec §5) |
| T10 | Student-level: group membership, student timetable | **Done** (elective course selection: not built) |
| T11 | Substitution ranking, effective timetable of a date, attendance-facing API | **Done** |
| T12 | AI natural language → constraint proposal | Not built (rules are typed data validated by the catalogue — ready for it) |

## Q. Risks

1. **Live grid vs published version** — `schedules` is both the working grid and what attendance
   implicitly follows. Versioning must not silently move lessons under running attendance (decision D2).
2. **Requirement double counting** — today a subject of a section given to two teachers counts the full
   weekly load for each (now surfaced by the Advisor as `subject_split_between_teachers`). Activities
   with co-teacher session counts fix it.
3. `timetable.periods` PK is SMALLINT and single-bell per school; multi-bell schedules need a
   bell-set concept, not a PK change (HD-TV-004).
4. Rooms are never assigned by the builder (`room_id` always null) → room constraints are untestable
   until the activity carries a room requirement.
5. Heuristic quality at scale is unknown — measure on the 45K seed before committing to CP-SAT.

## R. Decisions — resolved

D1–D5 approved by the owner on 2026-10-07 and implemented: explicit-FK tables (D1), version snapshots with
`schedules` as the working grid (D2), permissions `timetable.constraints.manage / generate / publish / approve`
and role `timetable_approver` (D3), approvals through `workflow.approval_requests` (D4), PHP heuristic behind
`TimetableSolverInterface` (D5). Records: ADR-021, ADR-022.
