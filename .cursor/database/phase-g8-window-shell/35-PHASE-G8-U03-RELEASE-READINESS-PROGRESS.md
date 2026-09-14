# G8 Release Readiness — Progress Checklist (Window Shell phase)

**Status:** IN PROGRESS  
**Branch:** `feature/phase-g8-window-shell`  
**Authority:** `production-readiness.md` + OPERATIONAL-TRANSITION-GATE G8

## Closed in this phase

- [x] Guest Operational Hub without auth (static / no school PII)
- [x] Central Window Manager shell (web-inertia desktop)
- [x] Shared Confirm Dialog for destructive UI actions
- [x] Negative guest auth tests for protected pages
- [x] Windows Desktop shortcut launcher (browser host only)

## Still open (true production G8)

- [ ] PostgreSQL replica / PgBouncer production topology
- [ ] Backup restore drill (full + PITR)
- [ ] Load test matrix recorded in `load-test-results.md`
- [ ] Monitoring alerts live
- [ ] Full regression suite green on CI with PostgreSQL

## HOLDs

No Electron/Tauri/Blazor without ADR · No auth bypass for tenant academic data
