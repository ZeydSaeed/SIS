# SIS Governance Map — Constitution v2.0 → Repository Artifacts

Maps the Permanent Engineering Constitution to **existing** rules and docs.  
Do **not** duplicate — follow the mapped artifact.

| Constitution § | Topic | Primary artifact |
|----------------|-------|------------------|
| 0–2, 61–63, 81–83 | Core directive, workflow, change report | `00-SIS-CONSTITUTION.mdc`, `SIS-CONSTITUTION.md` |
| 3–5, 24–25 | Laravel / business authority | `sis-core.mdc`, `clean-architecture.mdc`, `architecture-governance.mdc`, `ARCHITECTURE-STACK.md` |
| 6–7, 19–22 | UI contracts, design system | `UI-CONTRACT.md`, `react-inertia.mdc`, `02-ui-ux.mdc` |
| 15–18 | Window / dialog system | `WINDOW-CONTRACT.md`, `03-window-system.mdc` |
| 14, 45–47 | Responsive + adaptive | `04-responsive-adaptive.mdc`, `PLATFORM-CONTRACT.md` |
| 26–27, 30–31 | Security, auth, audit | `security.mdc`, `sis-core.mdc`, `docs/security/` |
| 28 | Database | `database-changes-mandatory.mdc`, `database-design.mdc`, `DATABASE-GOVERNANCE.md` |
| 29 | API | `api-conventions.md`, `08-api-governance.mdc` |
| 50 | Testing | `testing-strategy.md`, `09-testing-governance.mdc` |
| 53–55 | Modules / features | `MODULE-CONTRACT.md`, `FEATURE-DONE.md`, `WORK-PLAN.md` |
| 58–60, 78–79 | Change control, debt | `11-change-control.mdc` |
| 9–10, 40–45, 71–73 | Runtime / platform / adapters | `RUNTIME-CONTRACT.md`, `13-runtime-platform.mdc`, `14-framework-adapters.mdc` |
| 65 | Architecture baseline | `ARCHITECTURE-BASELINE.md`, `ARCHITECTURE-BASELINE.json` |
| Performance / optimization | Adaptive DB governance | `query-optimization.mdc`, `autonomous-optimization.mdc`, `DATABASE-ADAPTIVE-GOVERNANCE.md` |

## Agent Entry Points

1. `AGENTS.md`
2. `.cursor/brain/PROJECT.md`
3. `.cursor/architecture/SIS-CONSTITUTION.md` (full constitution)
4. `.cursor/architecture/README.md` (index)

## Bootstrap Status (2026-09-08)

| Artifact | Status |
|----------|--------|
| SIS-CONSTITUTION.md | Installed |
| UI / Window / Module / Runtime / Platform contracts | Installed (baseline = current repo) |
| Numbered Cursor rules 00–14 | Partial — merged with existing rules via this map |
| Application code | **Unchanged** (governance-only bootstrap) |
