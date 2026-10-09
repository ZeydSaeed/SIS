# Timetable Workbench — «الجدول الدراسي» as a planning & optimisation workbench

**Date:** 2026-10-09 · **Branch:** V2 · Builds on the engine (T0–T11, ADR-021 / ADR-022). This document lists what the
workbench adds, where each piece of data lives, and how the pieces fit.

## 1. Source of truth — the timetable copies nothing

| Data | Owner page | Stored in | Timetable reads it via |
|---|---|---|---|
| Teacher name, abbreviation, colour, academic title | «المعلمون» | `teachers.teachers` (+ `teachers.academic_titles`) | `displayCatalog` |
| Subject name, abbreviation, colour, type, hours | «المواد والمناهج» | `curriculum.subjects` | `displayCatalog` |
| Branch / department abbreviation, colour | «الفروع والاختصاصات» | `organization.branches` / `departments` | `displayCatalog` |
| Class / section abbreviation, colour, capacity | «الصفوف والشعب» | `enrollment.classes` / `sections` | `displayCatalog` |
| Room details, type, capacity, practical support | «الغرف الدراسية» (new) | `organization.rooms` + `organization.room_types` | board + `displayCatalog` |
| Period / break title, colour, show / print targets | «الاستراحات» (timetable) | `timetable.periods` | workspace |
| Cell layout, fields, fonts, sizes | «تنسيق الجدول» (timetable) | `timetable.configs.display` | `display.settings` |

Every entity's «الاختصار واللون» is written only by its owner's command (UpdateSubject fields, UpdateTeacherAppearance,
UpdateOrganizationAppearance, UpdateStructureAppearance). The timetable's context menu opens the same shared dialog
(`components/sis/appearance-fields.tsx`) against the owner's endpoint. A colour is a hue (0–359) on the governed card
palette — the stylesheet keeps lightness and saturation, so contrast is preserved; NULL = the default colour, and a
missing abbreviation shows a server suggestion (`AbbreviationSuggester`) that is never stored.

## 2. What was added

| Area | Pieces |
|---|---|
| Rooms page | `/organization/rooms`: server-side list (search, filters, sort, pagination), add / edit / out of service, managed types (system + school), context menu, double-click details, appearance |
| School day & breaks | Domain `BellScheduleCalculator`; `POST /timetable/periods/reshape`: insert (also before the first / consecutive), move, resize, remove (retire) a break, re-time one period with or without moving the next ones, fixed pattern. `SchoolDaySheet` |
| Display | `TimetableDisplaySettings` (layouts *corner · columns · subject · full*, visible fields, abbreviations, academic title, alignment, approved fonts, scale, weight, text colour, row height, column width, colour-by, period header). `FormatSheet` (🔍 «عدسة المعاينة والتنسيق»), apply on screen or save for everyone |
| Cell editing | Tabbed «تحرير الخلية» (content incl. room · format · teacher · subject · room · section · view · print), double-click, right-click menu, ribbon |
| Context menu | `components/sis/context-menu.tsx` (Shift+F10 / ContextMenu key, RTL, full screen aware) — lessons, empty cells (add / paste), period & break headers |
| Drag & drop | A refused drop explains why (teacher busy / cell taken) and offers the free cells (lighter days first) |
| «اختبار الجدول» | Domain `Testing/*`: advisor + auditor + placement (rooms, capacity, practical rooms, section capacity) + day (lengths, gaps, breaks, balance) + data (abbreviations) checks, five severities, causes, remedies, alternatives, «تجاهل / مراجعة لاحقاً» (`timetable.test_marks`), CSV report |
| «المساعد الذكي» | `RemedyFinder` — an explainable expert system (no external model): free slots, free rooms that fit, qualified teachers with spare load, the break to shorten for practical doubles, settings to raise — each with «لأن…». Remedies apply only after confirmation, through the normal endpoints |
| Generation | `GenerationStrategy`: complexity *normal / large / huge* (attempts with different seeds, iteration cap, ejection depth, budget; best attempt kept) and level *basic / relaxed / strict* (what is hard). Result summary in the generate sheet |
| Preview | Timetable type selector, zoom in / out / 100 % / fit width / fit page, print preview from the ribbon; breaks show / print per target |
| Excel import | «مركز الاستيراد» `/imports`: template → upload → queued validation → preview (row errors, in-file duplicates, create / update / skip) → commit through the owning handlers → report (created / updated / skipped / failed / errors) → error report download. Profiles: teachers, branches & departments, students, subjects & curriculum links. Dependency-free `.xlsx` / CSV adapter |

## 3. Decisions

- **No new dependency.** The spreadsheet adapter uses ext-zip / ext-xml; the context menu reuses the SIS list-menu look.
- **AI = explainable rules.** An LLM would be a new external runtime (ADR + approval); the assistant is deterministic and
  never writes without the user's confirmation.
- **Per-cell persistent formatting was not added:** formatting is per school-year so printed timetables stay consistent;
  the lens on a cell previews that cell with the table's settings.
- **Rooms stay owned by branches** (no `school_id` column), matching the existing schema; queries always join the branch.
- **Removed breaks are retired** (`status = 2`), never deleted — the period-number UNIQUE became partial on active rows.
- **Imports never overwrite students** (create-only; known students are skipped) — personal data is edited on its page.

## 4. Tests

`tests/Unit/Domain/BellScheduleCalculatorTest.php`, `DisplayAppearanceTest.php`, `TimetableWorkbenchDomainTest.php`,
`tests/Unit/Imports/ImportToolsTest.php`, `tests/Feature/Database/PostgreSql/TimetableWorkbenchPostgreSqlTest.php`.
