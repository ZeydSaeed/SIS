# SIS Runtime Contract

**Version:** 1.0  
**Date:** 2026-09-08  
**Constitution:** §7–10, §40–45, §71–73

---

## Current Runtime (installed)

| Runtime ID | Framework | Entry | Status |
|------------|-----------|-------|--------|
| `web-inertia` | React 19 + Inertia 3 | Laravel Vite bundle | **ACTIVE** |
| `api-json` | REST + Sanctum | `/api/v1/*` | **ACTIVE** |

---

## Future Runtimes (not installed — ADR required)

| Runtime ID | Host | Condition to introduce |
|------------|------|----------------------|
| `pwa` | Browser installable | Offline UX requirement + security review |
| `desktop-electron` | Electron shell | Desktop host requirement — hosts web UI only |
| `desktop-tauri` | Tauri shell | Same — no business logic in host |
| `spa-react` | Standalone React | Only if Inertia insufficient — same API contracts |
| `blazor-web` | Blazor WASM/Server | Explicit ADR + approval |
| `desktop-blazor-hybrid` | C# / .NET / Blazor Hybrid | Explicit ADR + approval — see `DESKTOP-UI-GOVERNANCE.md` |

**Intended desktop client path (when approved):** `clients/sis-desktop/`

**Rule:** Do NOT install frameworks for theoretical compatibility.

---

## Resolution Flow (conceptual)

```text
Capability detection (viewport, touch, pointer, keyboard, host)
        ↓
Presentation profile (desktop | tablet | mobile)
        ↓
UI adapter (Inertia today)
        ↓
Components / pages
```

Resolver MUST NOT contain business logic.

---

## Shared Across All Runtimes

```text
Authentication contract
Authorization / permissions
API request/response shapes
Validation error codes
Audit semantics
Design tokens (where UI exists)
Command names (STUDENT.CREATE, ENROLLMENT.CANCEL, …)
```

---

## Electron / Desktop Host Rule

Desktop host is **replaceable shell** only:

```text
SIS Web UI → Desktop Host (Electron/Tauri/Browser)
```

Host MUST NOT own: business rules, authorization, DB rules, window contract definitions.

---

## Runtime Registry

When adding a runtime, update this file with: id, framework, version, supported platforms, entry point, status.

Do not create duplicate registries elsewhere.
