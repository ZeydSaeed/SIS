# ADR-023: Offline Attendance Drafts (Service Worker + IndexedDB queue)

## Status
Accepted — 2026-10-12 (human-approved by the project owner in the attendance implementation session)

## Context
Teachers take attendance on phones in classrooms with unreliable connectivity. The web client is Inertia-only
(`react-inertia.mdc`, `15-ui-optimization-governance.mdc`: no ad-hoc `fetch` / client caches without an ADR), and an
attendance sheet must never be reported as sent to the administration unless the server confirmed it.

## Decision
1. **Scope:** only the attendance area (`/attendance/…`). A service worker `public/attendance-sw.js` is registered
   with scope `/attendance/` by the attendance pages; no other module is affected.
2. **Caching (read-only):** built assets (`/build/…`) cache-first after their first online load; attendance pages
   network-first with the last successful response as the offline fallback. Nothing is precached; no API data
   other than what the page itself rendered.
3. **Draft queue:** when a draft save cannot reach the server, the *changed marks only* (enrollment id, status,
   minutes late, note) are stored in IndexedDB (`sis-attendance` / `drafts`, one entry per sheet) with the sheet
   version, the idempotency key and the CSRF token of the session. No student names, photos or other PII.
4. **Sync:** on reconnect (page `online` event, page load, or Background Sync `sync` event where supported) the
   entry is POSTed as JSON to the existing draft endpoint (`POST /attendance/sheets/{id}/draft`,
   `Accept: application/json`) with its original idempotency key — a replay never applies twice; a stale version
   is rejected (409) and the entry is kept for the user to discard or re-apply after reloading. This is the only
   non-Inertia request in the module.
5. **Never offline:** submitting a sheet, review decisions, corrections, excuses and staff check-in require the
   server; their buttons are disabled offline. The UI distinguishes «saved on this device» from «saved on the
   server (version n)».
6. **Cleanup:** entries are deleted after a confirmed sync or an explicit discard.

## Consequences
- Business rules stay on the server (version guard, roster guard, permissions, windows); the queue is transport only.
- Devices keep a small local copy of unsynced marks until reconnect — acceptable (no names, session-bound CSRF).
- Background Sync is progressive enhancement; without it sync happens the next time an attendance page is open.
