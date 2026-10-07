# Timetable & Scheduling Engine

SIS-native timetable engine: it schedules what SIS already knows (sections, curricula, teaching assignments,
rooms, workshops, students) and never re-enters those facts. Built 2026-10-07 (phases T0–T11; decisions D1–D5
approved — ADR-021, ADR-022).

| Document | Content |
|----------|---------|
| [TIMETABLE-GAP-ANALYSIS.md](./TIMETABLE-GAP-ANALYSIS.md) | Discovery (T0), UI inventory, gap list with what is now closed |
| [TIMETABLE-DOMAIN-SPECIFICATION.md](./TIMETABLE-DOMAIN-SPECIFICATION.md) | Ownership, activity / time / constraint models, lifecycle, **implemented schema** |
| [TIMETABLE-SOLVER-SPECIFICATION.md](./TIMETABLE-SOLVER-SPECIFICATION.md) | Solver port, compiler, heuristic, modes, scoring, explainability, runs |
| [VOCATIONAL-TIMETABLE-SPECIFICATION.md](./VOCATIONAL-TIMETABLE-SPECIFICATION.md) | Workshops, splits, sequences, the §147 scenario (tested) |
| [VALIDATION.md](./VALIDATION.md) | Readiness advisor, conflicts, workload, quality |
| [API.md](./API.md) | Web (Inertia) writes, on-demand props, JSON API |
| [UI.md](./UI.md) | Ribbon, sheets, views, lesson tools |
| [TESTING.md](./TESTING.md) | Unit / PostgreSQL scenarios, stress measurements |
| [ASC-COMPATIBILITY-MATRIX.md](./ASC-COMPATIBILITY-MATRIX.md) / [EDUPAGE-COMPATIBILITY-MATRIX.md](./EDUPAGE-COMPATIBILITY-MATRIX.md) | Feature coverage |

## The flow

```text
SIS data (curriculum hours × teaching assignments, sections, enrollments, rooms, workshops)
  → «الأنشطة والمجموعات»  activities (from the curriculum or by hand), groups, joined classes, co-teachers
  → «القيود» / «الإتاحة» / «إعدادات الجدول»  rules with priorities, time-off, working days, limits
  → «الجاهزية والجودة»  READY / BLOCKED with reasons (advisor)
  → «توليد الجدول»  queued run (mode, scope, objectives, what-if) → review (unplaced + why, relaxed rules, diff)
  → apply to the working grid → manual edits (drag & drop, swap, shift, lock, «اقتراح أماكن»)
  → «الإصدارات والنشر»  snapshot → workflow approval → publish from a date → effective timetable
  → operations: «بديل ليوم» substitutes, effective-date API for Attendance, student timetable, CSV export
```

## Where the code is

| Layer | Path |
|-------|------|
| Domain | `app/Domain/Timetable/{Data,Services,Solver,Constraints,Specifications,ValueObjects,Events,Repositories}` |
| Application | `app/Application/Timetable/{Commands,Queries,Results,DTOs,Support,Contracts}` |
| Infrastructure | `app/Infrastructure/Persistence/Timetable/*`, `app/Infrastructure/Jobs/ExecuteTimetableGenerationJob.php`, `app/Infrastructure/Timetable/QueuedTimetableGeneration.php`, workflow hook `app/Infrastructure/Workflow/ApprovalEntityCompletionHookAdapter.php` |
| HTTP | `app/Http/Controllers/Timetable/{TimetablePageController,TimetableEngineController}.php`, `app/Http/Controllers/Api/TimetableEngineApiController.php`, `app/Http/Requests/Timetable/*` |
| UI | `resources/js/pages/timetable/{index,student}.tsx`, `resources/js/components/timetable/engine/*` |
| Schema | `database/migrations/2026_10_07_1000*`, `.cursor/architecture/database-blueprint.md` (schema `timetable`) |

## Operations

- **Deploy:** `php artisan migrate` then `php artisan db:seed --class=SecurityPermissionSeeder` (new permissions /
  role `timetable_approver`). Assign `timetable_approver` to whoever approves timetables.
- **Queue:** generation runs on the default queue (`ExecuteTimetableGenerationJob`, 1 try, 300 s timeout; the
  solver's own budget ≤ 120 s). Run a worker (`php artisan queue:work`); with `QUEUE_CONNECTION=sync` runs execute
  inline (dev / tests).
- **Approval flow:** the first submission creates a one-step «timetable_version» flow for `timetable_approver` if
  the school has none; schools may define their own multi-step flow in the workflow module.
