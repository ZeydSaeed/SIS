# SIS Window Contract

**Version:** 1.0  
**Date:** 2026-09-08  
**Constitution:** §15–18, §75

---

## Status

| Item | Status |
|------|--------|
| Window Manager (centralized) | **NOT IMPLEMENTED** |
| Multi-window desktop UX | **TARGET** |
| Current navigation | Inertia full-page + layouts |
| Dialogs | Radix Dialog / Alert patterns in components |

This contract defines the **target logical API** and **current gap**. Do not build per-module window systems.

**Desktop implementation target:** `DESKTOP-UI-GOVERNANCE.md` (Application Shell, Workspace, Docking — FUTURE / ADR required).

---

## Target: Window Manager API (logical)

```text
open(windowId, options)
close(windowId)
minimize / maximize / restore
move / resize
focus / bringToFront
dock / undock
persistState / restoreState
```

---

## Window Identity (stable logical IDs)

Use dotted stable IDs — never random DOM IDs alone:

```text
student.details
student.edit
enrollment.create
enrollment.details
report.transcript
```

---

## Window Descriptor (conceptual)

```json
{
  "windowId": "enrollment.details",
  "title": "Enrollment Details",
  "route": "/enrollments/{id}",
  "permissions": ["enrollment.view"],
  "resizable": true,
  "movable": true,
  "maximizable": true,
  "minimizable": true,
  "closable": true,
  "modal": false,
  "minimumSize": { "width": 480, "height": 360 }
}
```

---

## Dialog Contract

Use for: confirmation, short edit, warnings, destructive actions.

| Platform | Presentation |
|----------|--------------|
| Desktop | Centered dialog |
| Tablet | Adaptive dialog / split |
| Mobile | Full-screen or bottom sheet |

Large workflows → dedicated page/window, not tiny dialog.

---

## Current Implementation Guidance

Until Window Manager exists:

1. Use Inertia pages for primary workflows
2. Use shared Dialog components for confirmations
3. Assign stable `data-window-id` or route names for future migration
4. Do NOT embed independent z-index / drag logic in feature modules

---

## Persistence

When implemented, persist: position, size, workspace, dock state — **never secrets**.

---

## Windows Dev Environment

Primary desktop validation target: **Windows Desktop/Laptop** — verify keyboard, focus, multiple concurrent forms when Window Manager lands.
