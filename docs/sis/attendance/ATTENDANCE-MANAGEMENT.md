# Attendance & Absence Management (2026-10-11, extended 2026-10-12)

Extends the existing Attendance bounded context (sessions / records / RLS R1.7–R1.8A) into an operational workflow:
teacher day → open lesson → mark → draft / submit → administrative review → approve or return → reports & alerts,
plus teacher work attendance, excuses and versioned policies.

## Flow

```text
Teacher  /attendance/my ── open lesson (effective timetable of the date) ──▶ /attendance/sheets/{id}
         mark (roster = enrollments active in section/group on that date) ─▶ draft (versioned) ─▶ submit (complete, confirmed)
Reviewer /attendance/review ── start review / approve / return (reason) / reject (reason) / bulk approve (per-item versions)
Approved sheets change only through the official correction (attendance.correct, reason) or an approved excuse.
```

Sheet status (`attendance.sessions.sheet_status`) — transitions exist only in `Domain\Attendance\Services\AttendanceSheetWorkflow`:

| From | submit | start_review | approve | return | reject |
|------|--------|--------------|---------|--------|--------|
| 1 Draft | → 2 | | | | |
| 2 Submitted | | → 3 | → 4 | → 5 | → 6 |
| 3 Under review | | | → 4 | → 5 | → 6 |
| 5 Returned | → 2 (resubmission) | | | | |
| 4 Approved / 6 Rejected | final | | | | |

Session `status` keeps its R1.8A meaning: marking only while OPEN; approve / reject close the session.

## Guarantees

| Concern | Mechanism |
|---------|-----------|
| Teacher only on own lessons | `LessonAccessGuard` (scheduled / substitute of the date / co-teacher); reviewers act on behalf, recorded as `on_behalf` |
| Lesson must exist | `LessonSourceInterface` over `GetEffectiveTimetableHandler` (published version or working grid + date substitution); holidays refused |
| History not rewritten by timetable edits | `lesson_snapshot` frozen at open; `schedule_id` FK |
| One sheet per lesson · date | partial UNIQUE + `INSERT … ON CONFLICT DO NOTHING` |
| No duplicate student row | existing UNIQUE (session, student, year) + payload `distinct` + roster guard |
| Stale tab / old retry | `version` optimistic check under `SELECT … FOR UPDATE`; same idempotency key replays the first result |
| Double submit / double approve | idempotency (key namespaced per user) + transition guard |
| Policy changes | new version row; sessions keep `policy_id`; existing records never recalculated |
| Excuse ≠ approval | pending until a reviewer decides; approval converts only `absent` rows in range, versions + history per sheet, before/after audited |
| Evidence ≠ status ≠ approval | `marked_via` / staff `method`; a check-in code never approves anything |
| Tenant isolation | school + year from session context only; every query school-scoped; FORCE RLS on all new tables; central dashboard reads each school under its own RLS scope (`SchoolContextScope`) |
| Audit | `sheet_events` (in-transaction) + outbox `AttendanceWorkflowEvent` → `audit.audit_logs` (before/after) + `security_audit_logs` per HTTP action |
| Notifications | outbox listener → `communication.messages` (in-app, existing module), after commit only |

## Permissions (config/security.php)

| Permission | attendance_teacher | attendance_manager | attendance_viewer |
|------------|:-:|:-:|:-:|
| attendance.sheet.submit | ✓ | ✓ | |
| attendance.sheet.review | | ✓ | |
| attendance.excuse.submit / .review | | ✓ | |
| attendance.staff.record | | ✓ | |
| attendance.staff.checkin | ✓ | | |
| attendance.reports.view | | ✓ | ✓ |
| attendance.alerts.manage / attendance.policy.manage | | ✓ | |
| link a teacher's user account (`teachers.manage`) | | (teachers_manager) | |

**Deploy:** `php artisan migrate` then `php artisan db:seed --class=Database\\Seeders\\SecurityPermissionSeeder` (project convention — permissions are registered by the seeder, not by migrations). A teacher sees their lessons only when `teachers.teachers.user_id` links their account and `teacher_schools` places them in the school for the year.

## Configuration

`config/attendance.php` — `SIS_ATTENDANCE_TIMEZONE` (default `Asia/Baghdad`, confirmed 2026-10-12) is used until a school saves its policy; page sizes;
`pdf_font_path` (optional Tahoma TTF for PDF exports — otherwise mPDF's bundled DejaVu Sans; governance fonts noted).

## Alerts (deterministic, reviewable)

`student_absence_count`, `student_absence_rate`, `student_subject_absence`, `student_practical_absence`, `student_late_repeat`,
`student_session_conflict`, `section_absence_spike`, `teacher_sheet_missing`, `teacher_sheet_returned_repeat`,
`teacher_absent_with_lessons` — thresholds from the policy; hourly job `attendance:alerts` (queue) + on demand; deduplicated per
rule · subject · day. No machine-learning / AI scoring is implemented.

## Rates

`AttendanceStatistics::FORMULA` — attendance = (present + late + left early) / decided; absences include excused; unmarked rows never count.
Shown on every report and dashboard.

## Database change record (DATABASE-CHANGE-CHECKLIST / schema-change-impact)

- Migration `2026_10_11_100000_create_attendance_review_lifecycle` — additive only; verified up → rollback → up on PostgreSQL.
- `attendance.sessions`: +17 nullable/defaulted columns (metadata-only on PG); OPEN natural-key unique re-keyed with `COALESCE(schedule_id, 0)` (legacy semantics unchanged); 3 indexes.
- `attendance.records` (partitioned): +3 columns (constant defaults, no rewrite), status CHECK 0..5 (pre-checked: aborts if any row outside 1..5), `(session_id)` index.
- 6 new tables, FORCE RLS fail-closed, DELETE rejected by trigger; academic history never hard-deleted.
- Blueprint 102 → 108 objects; indexing-matrix updated. No EXPLAIN baselines yet for the new paths (capture under real load).
- Blast radius: legacy attendance API/web keep working; legacy marking now refused on submitted/approved sheets (`attendance.sheet_not_editable`) and bumps the sheet version.

## Extensions (2026-10-12)

| Feature | Implementation |
|---------|----------------|
| Exceptional lesson | `OpenExceptionalAttendanceCommand` (reviewers only, reason required, section/subject/teacher/period validated against the school & year, `origin = 3`, one per section · subject · period · date) |
| QR check-in | the rotating code is also rendered as a QR (`bacon/bacon-qr-code`, server SVG) of `/attendance/staff?code=…&via=qr`; scanning only pre-fills the window — the teacher still submits; `method = 6` |
| NFC check-in | optional per policy (`nfc_enabled`, default off); station token = 24 hex chars, HMAC-hashed (`checkin_codes.kind = 2`), 1-year validity; Web NFC read/write in the browser; no location collected; `method = 7` |
| Teacher ↔ user account | `LinkTeacherUserAccountCommand` (by e-mail, `teachers.manage`, one teacher per account, audited) |
| Queued exports | `RequestAttendanceReportExportCommand` → `GenerateAttendanceReportExportJob` (CSV with BOM / PDF via `mpdf/mpdf`, RTL) → private `local` disk; download only by the requester in the same school |
| Excuse documents | upload (PDF / image ≤ 10 MB) stored through the Documents module (`UploadDocumentHandler`) in the same transaction |
| Risk indicator | `AcademicRiskIndicator` — transparent points (rate, trend, streak, subjects, practical) with reasons; dashboard + `risk` report; for human review only, never an automatic decision |
| Offline drafts | ADR-023 — service worker (`/attendance/` scope) + IndexedDB queue + Background Sync, same idempotency key and version (conflicts surface, never overwrite) |
| UI | every page on the shared chrome: «تحرير» tab = page actions + attendance sections (coloured icons), «الصفحة الرئيسية» = filters (multi-value), standard tables / windows / dialogs |

Migration `2026_10_12_100000_extend_attendance_evidence_and_exports` — additive: policy flags (defaults keep current behaviour), `checkin_codes.kind/label`, new `attendance.report_exports` (FORCE RLS). Blueprint 108 → 109.

## UI conventions and hardening (2026-10-13)

- Shared chrome `resources/js/components/attendance/attendance-chrome.tsx`: every table has «تحديد» / «تحديد الكل», «عرض» (all columns, previous / next over several rows) and «تحرير» in the «المحدد» ribbon group; zoom («العرض»: تكبير / تصغير / 100%), columns resized on header edges, rows on their bottom edges (`use-resizable-table-rows.ts`), the page size fits the visible height (server `per_page` 3…100) with the standard pagination; pages scroll with a hidden scrollbar.
- Guidance, warnings, empty results and refusals open in the shared message dialog (`useAttendanceMessages`), never as text on the page; the Home-tab filters are a three-column grid; windows use two columns.
- Inputs: query-string dates must exist in the calendar (`CalendarDate`), free text and enumerations are read type-safely (`AttendanceFilterInput::text / date`), code filters are bounded to their SMALLINT range — malformed URLs return the page, never an HTTP 500.
- Scenario matrix `AttendanceScenarioMatrixPostgreSqlTest` (group `scenario`): 20 teaching units (7 classes × A/B/C, 4 branches, 8 departments, theory / practical, co-teachers), a week of lessons, every lifecycle path plus staff, excuses, exceptional lessons and a 1,200-request fuzz; `SIS_ATTENDANCE_SCENARIO_SEED` replays another random walk. `AttendanceErrorDialogCoverageTest` guarantees every raised error code has its Arabic dialog text.

## Not implemented (by decision or dependency)

- Kiosk / biometric adapters; geolocation (not collected by decision).
- Automatic clean-up of old export files (operational retention to be decided).
- Machine-learning risk prediction (the indicator is rule-based and explained).
