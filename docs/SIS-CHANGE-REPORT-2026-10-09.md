# SIS CHANGE REPORT — Timetable Workbench (2026-10-09)

**Scope:** «الجدول الدراسي» rebuilt into a planning & optimisation workbench, with the data each feature needs placed in
its owning page. **Branch:** V2 (not committed). Detailed design: [docs/timetable/WORKBENCH.md](./timetable/WORKBENCH.md).

## 1. Classification & governance

App feature + database + UI + architecture-sensitive (new tables, new Application area `Imports`). Read: CLAUDE.md,
AGENTS.md, application-feature & database-change skills, clean-architecture, react-inertia, 02-ui-ux,
17-color-typography, 11-change-control, database blueprint / indexing matrix, timetable docs (system report, gap
analysis, ASC matrix). No new framework, runtime or package.

## 2. Database (additive only — 3 migrations, all reversible, verified up → down → up)

| Migration | Change |
|---|---|
| `2026_10_08_150000_add_display_attributes_and_room_catalogue` | `abbreviation` + `color_hue` on subjects, teachers, branches, departments, rooms, classes, sections; `teachers.academic_titles` (seeded) + `teachers.academic_title_id`; `organization.room_types` (system + school types, seeded); room details (number, building, floor, location, department, managed type, practical support, equipment, suitable for, notes); existing rooms backfilled to system types |
| `2026_10_08_150100_extend_timetable_periods_display_and_test_marks` | periods: name, abbreviation, colour, show_in / print_in bitmasks, status (retire); period-number UNIQUE → partial on active rows; `timetable.configs.display`; `timetable.test_marks` (FORCE RLS, no delete) |
| `2026_10_09_100000_create_documents_import_batches` | `documents.import_batches`, `documents.import_rows` (FORCE RLS, no delete) |

Blueprint count 97 → 102; `database-blueprint.md` and `indexing-matrix.md` updated.

## 3. Backend (Clean + DDD + CQRS)

- **Domain:** `DisplayAppearance`, `AbbreviationSuggester`, `RoomKind`, `RoomDetails`, `RoomCatalogueGuard`,
  `TeacherTitleGuard`, `PeriodPresentation`, `BellScheduleCalculator`, `TimetableDisplaySettings`,
  `GenerationStrategy`, `Testing/{TimetableTestRunner, RemedyFinder, PlacementChecks, DayChecks, DataChecks, TestIssue}`.
- **Application:** Organization (SaveRoom, ChangeRoomStatus, SaveRoomType, ChangeRoomTypeStatus,
  UpdateOrganizationAppearance, GetRoomCatalogue), Teachers (UpdateTeacherAppearance, ListAcademicTitles, title in
  Register/Update), Curriculum (subject display fields), Enrollment (UpdateStructureAppearance, GetStructureAppearance),
  Timetable (ReshapeSchoolDay, SaveTimetableDisplay, MarkTimetableTestIssue, TestTimetable, GetTimetableDisplayCatalog;
  GenerationRunner uses the strategy), Imports (engine, profile contract + 4 profiles in their owning contexts,
  Upload / Commit / Cancel / Parse / ExecuteCommit, GetImportCenter, BuildImportSheet).
- **Infrastructure:** Eloquent adapters, `SimpleSpreadsheet` (xlsx / csv, no dependency), queued `ParseImportJob` /
  `CommitImportJob` (school RLS context re-bound).
- **HTTP:** `RoomCataloguePageController`, `ImportPageController`, new actions on Teacher / ClassSection / Timetable /
  TimetableEngine controllers; FormRequests with idempotency keys; sensitive fields prohibited.

## 4. Frontend (existing design system only)

New: `pages/organization/rooms.tsx`, `pages/imports/index.tsx`, `components/sis/{context-menu, appearance-fields}.tsx`,
`components/timetable/{display-settings.ts, lesson-card-content, format-sheet, test-sheet, school-day-sheet}.tsx`.
Changed: timetable page (display-aware cards, context menu, double-click tabbed cell editor with room, drop
alternatives, view-aware breaks, ribbon groups, preview zoom / type), generate sheet (complexity, constraint level,
result summary), places sheet (link to rooms), teachers / curriculum / branches / classes-sections pages («الاختصار
واللون», academic title), title-bar entries. CSS: additive blocks only (keyed on new classes / data attributes); the
default grid renders as before. Colours: hues on the governed card scale; fonts: the four approved families only.

## 5. Security & integrity

Server-side authorization per owner (manageSchools / manageConstraints for rooms, manageCurriculum, manageTeachers,
enrollment update, managePeriods, student create for imports); tenant isolation by school context, FORCE RLS on new
school tables, room / room-type rows always scoped through the school; no hard deletes (retire / cancel by status);
idempotency on every write; outbox events on owner writes; imports write only on confirmation and only through the
owning handlers (students create-only).

## 6. Validation

| Check | Result |
|---|---|
| New unit tests (BellScheduleCalculator 11, DisplayAppearance 5, TimetableWorkbenchDomain 5, ImportTools 3) | pass |
| New PostgreSQL feature test `TimetableWorkbenchPostgreSqlTest` (6) | pass |
| Full PostgreSQL suite | 579 tests, 575 pass — the 4 known pre-existing failures only |
| `architecture:validate --fitness` | no new violation (remaining items pre-existing; `UpdateTeacherHandler` CC 11 unchanged from HEAD) |
| `security:validate` | only pre-existing `SEC-DEP-001` (composer audit) |
| `npm run types:check` | 22 errors = pre-existing baseline, none in new / changed timetable files |
| `npm run build` | success |
| Migrations up / down / up (isolated DB) | pass |
| Live browser check | not done (site not reachable from this environment) |

## 7. Not done / technical debt

- AI assistance is a deterministic expert system; an LLM integration needs an ADR (new external runtime).
- Formatting is per school-year (no per-cell persisted overrides); the master print view (`master-timetable.tsx`) keeps
  its own cell style.
- Student timetable is not a preview type (it stays the `/timetable/students/{id}` page).
- Academic titles have no management UI (seeded reference data).
- TECHNICAL DEBT: `TimetablePlaceGuard` allows 150-char room names while `organization.rooms.name` is VARCHAR(100)
  (the new rooms page enforces 100).
