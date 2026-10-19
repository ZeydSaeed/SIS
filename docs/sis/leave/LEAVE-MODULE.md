# «الإجازات والعطل» — Leave, Holidays & Absence Management

> Status (2026-10-19): implemented on branch `V2`, not committed. Migration `2026_10_18_120000_create_leave_schema`
> applied to `sis_test` and — with the user's approval — to `sis`; `SecurityPermissionSeeder` run on `sis`; roles
> `leave_manager` + `leave_approver` granted to user 1 in schools 1–3 (no other account has a leave role yet).

## 1. Scope and reuse

| Need | Where it lives | Decision |
|------|----------------|----------|
| Staff leave (teachers + administrative employees) | **new** `leave` schema | types · versioned policies · requests · history · balance ledger |
| Student leave | `attendance.excuses` (existing) | reused — approval turns «غائب» into «غياب بعذر» with an official correction; never «present» |
| Official / school holidays | `academic.holidays` (existing, «الاجازات» page) | reused — leave counts working days without them |
| Attachments | `documents.files` (existing) | reused, `entity_type = 'leave_request'`, module-owned (generic API refuses it) |
| Lesson cover | `timetable.schedule_exceptions` + `SuggestSubstitutes` (existing) | reused — cover is assigned through the timetable's own command and permission; the published timetable is never changed |
| Approvals | own lifecycle + `leave.request_events` | the workflow engine's hooks for other entities are **HOLD** (blueprint `workflow.approval_requests`) |
| Notifications | outbox event `LeaveChanged` (ids / statuses only) | no e-mail / SMS sending added (communication send is not live) |

## 2. Lifecycle

```
draft ──submit──▶ pending ──approve (last level)──▶ approved ──cancel──▶ cancelled
  ▲                 │  ├──reject (note)──▶ rejected
  │                 │  └──info (note)──▶ info required ──resubmit──▶ pending
  └──── edit ───────┘  (draft / info required only)
draft · pending · info required ──withdraw──▶ withdrawn
```

Rules (server-side, `Domain/Leave` + `Application/Leave`):

- Dates inside the academic year; end ≥ start; ≤ 366 days. Hours: one working day, quarter hours, ≤ 12.
- Quantity counted on the server: working days = timetable working week (`timetable.configs`, default Sun–Thu)
  minus holidays in force (global + school), or calendar days per policy. Zero days → refused.
- Submit: document required by policy → refused without one; overlap with a pending / returned / approved request of
  the same person → refused (per-person advisory lock); balance (policies that deduct) must cover it — open requests
  hold their quantity back.
- Decide: the request row is locked and its version must match; nobody approves their own leave; with 2–3 levels each
  level needs a different approver; reject / info need a note. The final approval deducts once (ledger unique
  `(request_id, entry_type)` + idempotency key) under the frozen policy version.
- Cancel (approved only, note required): status «ملغاة», one restoration entry; history kept.
- Nothing is deleted: DELETE triggers on every table; events and ledger reject UPDATE.

## 3. Permissions (`config/security.php`)

| Permission | Meaning |
|-----------|---------|
| `leave.view` | the school's leave (dashboard, all requests, calendar) |
| `leave.request.create` | own requests (teacher / employee linked to the account) |
| `leave.request.manage` | requests on behalf of any staff member |
| `leave.request.approve` | approve / reject / return (never own) |
| `leave.request.cancel` | cancel approved leave |
| `leave.policy.manage` | types and policy versions |
| `leave.balance.view` / `leave.balance.adjust` | balances of all staff / entitlements and audited adjustments |
| `leave.sensitive.view` | reason, type and attachments of sensitive (medical) leave of others |
| `leave.reports.view` | reports |

Roles: `leave_requester`, `leave_viewer`, `leave_approver`, `leave_manager` (manager ≠ approver — separation of
duties). No existing role receives them automatically. Lesson cover keeps the timetable's `timetable.exception.create`.

## 4. HTTP (Inertia, `require.school.context`)

| Method | Path | |
|--------|------|-|
| GET | `/leave` | dashboard (cards open the matching list) |
| GET | `/leave/requests` | list; optional props `detail` (`?open=`), `coverage` (`?coverage=`), `substitutes` (`?substitute=&date=`) |
| POST / PATCH | `/leave/requests`, `/leave/requests/{id}` | save draft (+ optional file, + optional `submit=1`) |
| POST | `/leave/requests/{id}/submit · decide · withdraw · cancel · attachments` | lifecycle |
| GET | `/leave/requests/{id}/attachments/{document}` | download (404 when masked / not visible) |
| GET / POST | `/leave/balances`, `/leave/balances/adjust`, `/leave/balances/grant` | balances, ledger (`?ledger=kind:id`) |
| GET / POST / PATCH | `/leave/types`, `/leave/types/{id}`, `…/status`, `…/policy`, `/leave/types/suggested` | types and policies |
| GET | `/leave/calendar?month=YYYY-MM&view=&layer[]=` | holidays · staff leave · students' excuses (with excuse rights) |
| GET | `/leave/reports?report=by_person|by_type|pending|approval_time|holidays` | reports (print from the browser) |

Every write needs `X-Idempotency-Key`; refusals return a localized `leave.*` code.

## 5. Integration

- **Attendance:** the staff attendance day shows «إجازة معتمدة #n» for a teacher on approved leave and lists their
  lessons for cover — read only (`ApprovedLeaveSourceInterface`); no attendance record is written by an approval.
  Holidays taken out of the calendar no longer close the attendance day.
- **Timetable:** «الحصص المتأثرة» lists the teacher's lessons (lead or co-teacher) on each working day of the leave from
  the effective timetable, with the substitute already assigned; «اقتراح بدلاء» uses the timetable ranker minus
  teachers on approved leave that day; «تعيين البديل» posts to `/timetable/schedules/{id}/substitute` (clash checks,
  permission and audit of the timetable).

### In-app notifications («الإشعارات» و«بانتظار موافقتي»)

Written inside the same transaction as the change they announce (`LeaveNotifier`): a submitted request asks every
approver of the school (holders of `leave.request.approve`, never the requester nor the actor); a level passed tells the
requester and asks the next approvers; approval / rejection / information request / cancellation tell the requester and
whoever entered the request. A notification holds ids and a kind only. `/leave/notifications` lists the user's own, marks
them read (own only), and opens the request; every leave page shows two counters on its rail — unread notifications and
requests awaiting the user's decision (`/leave/requests?awaiting=1`: pending, not their own, and not a round they already
approved a level of). No e-mail / SMS / WhatsApp: that needs a channel decision.

## 6. Not implemented / reserved

| Item | Reason |
|------|--------|
| Export to file (CSV / Excel) | heavy exports must be queued (rule 7); the attendance export pipeline is attendance-specific — reports print from the browser for now |
| E-mail / SMS / WhatsApp notifications | needs a channel decision; in-app notifications exist, events are staged in the outbox |
| Workflow-engine approvals | hooks for other entities are HOLD |
| Partial cancellation (early return) | a cancellation restores the whole deduction; an adjustment can correct the balance with a reason |
| Accrual (monthly earning) | entitlements are granted per year from the policy; adjustments cover the rest |
| One request across several schools | a request belongs to the school it is filed in (RLS); a teacher shared by two schools files in each |
| Periods (حصص) as a unit | days and hours only |
| Browser / mobile E2E tests | not run in this environment (no browser tooling) |

## Navigation, dialogs and demo data

- The module is «الإجازات والعطل», placed under «الحضور» in the sidebar. The former stand-alone holidays page and `/holidays` route are gone: holidays live inside the module as the «العطل» section (`/leave/holidays`, routes `leave.holidays.*`).
- Refused actions open a dialog whose tone and title come from `errors.presentation` (ar.ts): «تنبيه / راجع بيانات الطلب» for data the user can fix, «خطأ / تعذّر تنفيذ الإجراء» for actions that cannot be done. `request_missing` / `type_missing` replace the former `*_not_found` codes (a dialog, not a 404 page).
- `php artisan db:seed --class=LeaveDemoSeeder` fills a school with ~100 requests (every status and approval path), holidays and deliberate refusals through the real handlers. It skips a school that already has requests (`LEAVE_DEMO_FORCE=1` adds another batch).
