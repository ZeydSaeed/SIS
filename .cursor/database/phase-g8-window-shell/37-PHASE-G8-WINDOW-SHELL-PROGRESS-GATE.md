# Phase G8 Window Shell — Progress Gate (20 steps)

**Branch:** `feature/phase-g8-window-shell`  
**Predecessor tip:** `feature/phase-operational-ui` @ `e20e9d2`

## Delivered

| # | Unit | Result |
|---|------|--------|
| 1 | Phase start authorization | PASS |
| 2 | WIN-SHELL-U01 Foundation | PASS |
| 3 | WIN-SHELL-U02 Frame chrome | PASS |
| 4 | WIN-SHELL-U03 Workspace | PASS |
| 5 | WIN-SHELL-U04 Open from hub | PASS |
| 6 | WIN-SHELL-U05 Focus/minimize | PASS |
| 7 | WIN-SHELL-U06 Persist geometry | PASS |
| 8 | DIALOG-U01 ConfirmDialog | PASS |
| 9 | DIALOG-U02 Attendance close | PASS |
| 10 | DIALOG-U03 Grade void | PASS |
| 11 | GUEST-HUB-U01 Public /hub | PASS |
| 12 | GUEST-HUB-U02 Home / | PASS |
| 13 | GUEST-HUB-U03 Login CTAs | PASS |
| 14 | DESKTOP-ICON-U01 Assets | PASS |
| 15 | DESKTOP-ICON-U02 Install script | PASS |
| 16 | DESKTOP-ICON-U03 Launch script | PASS |
| 17 | G8-U01 Guest blocked | PASS |
| 18 | G8-U02 Guest hub access | PASS |
| 19 | G8-U03 Readiness checklist | PASS WITH CONDITIONS |
| 20 | Progress gate | PASS WITH CONDITIONS |

## Security note

Guest hub is **static** (gates + navigation). School-scoped academic pages remain behind `auth` + `verified` + school context. No tenant PII without login.

## Runtime note

Desktop icon launches **browser host** to `http://sis.test/hub?desktop=1`. Electron/Tauri/Blazor remain ADR HOLD.
