# PHASE AR-RTL OPS UI — Progress Gate (20 steps)

**Status:** CLOSED  
**Branch:** `feature/phase-ar-rtl-ops-ui`  
**Tip:** see git log on branch  

## Units completed

| # | Unit | Result |
|---|------|--------|
| 0 | Start authorization | PASS |
| 1 | AR-U01 Locale/dir | PASS |
| 2 | AR-U02 i18n catalog | PASS |
| 3 | AR-U03 Hub Arabic | PASS |
| 4 | AR-U04 Window chrome | PASS |
| 5 | AR-U05 ConfirmDialog | PASS |
| 6 | AR-U06 Sidebar | PASS |
| 7 | AR-U07 Dashboard | PASS |
| 8 | AR-U08 Enrollments | PASS |
| 9 | AR-U09 Attendance | PASS |
| 10 | AR-U10 Results | PASS |
| 11 | AR-U11 Exams/Grades | PASS |
| 12 | AR-U12 Reports/Teachers/Timetable/Students | PASS |
| 13 | AR-U13 Dialog RTL close | PASS |
| 14 | AR-U14 Numbers LTR helper | PASS |
| 15 | AR-U15 Desktop shortcut Arabic | PASS |
| 16 | AR-U16 Segoe UI font | PASS |
| 17 | TEST Guest hub ar/rtl | PASS (3 tests) |
| 18 | LAUNCH Desktop hub | PASS |
| 19 | Progress gate | PASS |

## Validation

- `php artisan test --filter=PhaseArRtlGuestHubTest` → 3 passed
- `npm run build` → success
- `GET http://sis.test/hub?desktop=1` → 200, `lang=ar`, `dir=rtl`
- Desktop shortcut installed (Arabic display name via ASCII temp + rename)
- Guest hub without login; school data routes remain auth-gated

## HOLDs unchanged

No Electron/Tauri/Blazor · No guest PII · PDF transcript HOLD · Payroll HOLD
