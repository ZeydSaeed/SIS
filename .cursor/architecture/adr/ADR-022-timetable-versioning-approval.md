# ADR-022: Timetable Versions as Immutable Snapshots, Approved through the Workflow Module

## Status
Accepted — 2026-10-07 (decisions D1, D2, D3, D4, human-approved)

## Context
A published timetable must never change silently, history must answer «what did section X have on date D»,
attendance already reads the live grid (`timetable.schedules`), and SIS has an official approval system
(`workflow.approval_requests`). Re-keying the live grid by version would rewrite its partial unique indexes and
move lessons under running attendance.

## Decision
1. **Working grid stays `timetable.schedules`.** Generation runs and manual edits change it; it is the draft.
2. **Versions are snapshots.** `timetable.versions` (+ `version_entries`, immutable by trigger: UPDATE and DELETE
   rejected). Lifecycle: draft → review → approved / rejected → published → superseded; archived for
   non-published ones. «Active» = the published version whose dates contain today (computed).
3. **Approval = workflow.** `SubmitTimetableVersionCommand` opens an approval request (`entity_type =
   timetable_version`) on the school's active flow; if none exists a one-step flow for the new
   `timetable_approver` role is created. Decisions go through `DecideApprovalRequestHandler` (role check
   authoritative); `ApprovalEntityCompletionHookAdapter` moves the version in the same transaction.
4. **Publishing** sets `effective_from`; the previous published version is superseded the day before and keeps
   governing its own dates (`EffectiveVersionSelector`). Restore copies a version back into the working grid.
5. **Stale detection.** Each version keeps the source fingerprint (requirements, activities, periods, sections,
   teachers, settings); a differing live fingerprint shows «may be affected by source-data changes» — never an
   automatic regeneration.
6. **Permissions** (config + seeder): `timetable.constraints.manage`, `timetable.generate`, `timetable.publish`,
   `timetable.approve`; role `timetable_manager` gains the first three, new role `timetable_approver` holds approve.

## Consequences
- No change to attendance contracts; effective timetable of a date via `GetEffectiveTimetableQuery` / API.
- History grows by one snapshot per saved version (≈ 1–2 k rows per school-year version) — negligible at 45K scale.
- Existing installations need `php artisan migrate` + `php artisan db:seed --class=SecurityPermissionSeeder`.
