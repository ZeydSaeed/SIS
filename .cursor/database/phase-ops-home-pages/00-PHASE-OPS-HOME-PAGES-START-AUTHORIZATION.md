# Phase Ops Home Pages — Start Authorization

**Status:** OPEN
**Branch:** `feature/phase-ops-home-pages`
**Predecessor:** `feature/phase-ar-rtl-ops-ui` @ `ef9144b`

## Intent

1. Adopt authenticated `/dashboard` as primary home after login.
2. Guest `/` and `/hub` remain login-free Window Manager shell (no school PII).
3. Complete Arabic RTL operational pages: students, attendance, timetable (+show), exams, teachers (+show), enrollments, grades, results, reports.
4. Desktop shortcut opens guest hub windows shell.

## HOLDs

No guest PII · No Electron/Blazor · Fortify home stays `/dashboard`
