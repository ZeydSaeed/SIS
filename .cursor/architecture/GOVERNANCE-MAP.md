# SIS Governance Map — Constitution v2.0 → Repository Artifacts

Maps the Permanent Engineering Constitution to rules, docs, validators, and CI.  
Do **not** duplicate normative text — follow the mapped artifact.

**Phase 0.2 status:** Governance hardened — precedence explicit, architecture always-applied.

---

## Governance Precedence (Mandatory)

```text
00-SIS-CONSTITUTION.mdc          (alwaysApply — index + workflow + change report)
        ↓
01-ARCHITECTURE.mdc              (alwaysApply — mandatory architecture principles)
        ↓
sis-core.mdc                     (alwaysApply — stack + non-negotiables)
        ↓
15-ui-optimization-governance.mdc (alwaysApply — mandatory UI optimization evaluation)
        ↓
clean-architecture.mdc           (app/** — layer rules)
architecture-governance.mdc      (app/** — feature workflow, no CRUD default)
ARCHITECTURE-STACK.md            (authoritative layer diagram)
        ↓
10-MODULES.mdc + MODULE-CONTRACT.md
        ↓
security.mdc · database-* · 08-api-governance · 09-testing · 12-DOCUMENTATION
02-ui-ux · 03-window · 04-responsive · 13-runtime-platform · 14-framework-adapters
        ↓
react-inertia.mdc                (React/Inertia implementation)
laravel-patterns.mdc             (Infrastructure Laravel glue — SUBORDINATE)
query-optimization.mdc · autonomous-optimization.mdc
        ↓
11-change-control.mdc            (alwaysApply — change safety)
```

**Rule:** Framework conventions never override SIS architecture.

### Governance map ordering semantics (Phase 0.4)

The vertical chain above is **descriptive / documentary** — it maps artifacts for navigation. It is **not** a conflict-resolution mechanism where a later-listed rule weakens, overrides, or disables an earlier mandatory rule.

| Principle | Meaning |
| --------- | ------- |
| **Mandatory alwaysApply** | `15-ui-optimization-governance.mdc` has `alwaysApply: true` (see table below). It remains mandatory on every session. |
| **Security & architecture first** | Constitution, architecture, security, database, API, module, and UI/UX governance take precedence over optimization (`UI-OPTIMIZATION-GOVERNANCE.md` §1). |
| **React/Inertia subordinate** | `react-inertia.mdc` is path-scoped implementation guidance. It does **not** override Rule 15. |
| **Rule 15 subordinate to higher tiers** | Rule 15 does **not** override security, authorization, tenant isolation, domain/API contracts, accessibility, or approved UI behavior. |
| **Conflict resolution** | If two rules appear to conflict, resolve per `UI-OPTIMIZATION-GOVERNANCE.md` §1 and `GOVERNANCE-MAP.md` precedence — **not** by assuming the later-listed rule wins. |
| **Correctness > optimization** | Security, business correctness, architecture constraints, PII, tenant isolation, and accessibility all outrank performance optimization. |

### laravel-patterns.mdc Resolution (G-001)

| Attribute          | Value                                                                         |
| ------------------ | ----------------------------------------------------------------------------- |
| Status             | **ACTIVE — aligned subordinate guidance**                                     |
| Applies to         | Infrastructure persistence, jobs, thin HTTP glue                              |
| Does NOT authorize | Service-class business logic, Model+Controller CRUD, Domain rules in Eloquent |
| Overrides          | Nothing above `laravel-patterns` in precedence chain                          |

---

## Always Applied vs Path-Scoped

**AlwaysApply registry (`alwaysApply: true`):** `00-SIS-CONSTITUTION.mdc` · `01-ARCHITECTURE.mdc` · `sis-core.mdc` · `15-ui-optimization-governance.mdc` · `11-change-control.mdc`

| Rule                             | alwaysApply | globs                           | Role                                         |
| -------------------------------- | ----------- | ------------------------------- | -------------------------------------------- |
| `00-SIS-CONSTITUTION.mdc`        | ✅          | —                               | Constitution index, workflow, change report  |
| `01-ARCHITECTURE.mdc`            | ✅          | —                               | Mandatory architecture bridge                |
| `sis-core.mdc`                   | ✅          | —                               | Stack, academic ops, DB/perf non-negotiables |
| `11-change-control.mdc`          | ✅          | —                               | Change safety, debt, approval gates          |
| `clean-architecture.mdc`         | ❌          | `app/**/*.php`                  | Layer placement                              |
| `architecture-governance.mdc`    | ❌          | `app/**/*.php`                  | Feature scaffold workflow                    |
| `laravel-patterns.mdc`           | ❌          | `app/**/*.php`                  | Infrastructure Laravel patterns              |
| `security.mdc`                   | ❌          | app/routes/config/database      | Security guidance → baseline                 |
| `database-changes-mandatory.mdc` | ❌          | database/**, models             | DB change workflow                           |
| `database-design.mdc`            | ❌          | database/**                     | PostgreSQL types/constraints                 |
| `query-optimization.mdc`         | ❌          | app/**                          | Query performance                            |
| `autonomous-optimization.mdc`    | ❌          | —                               | Adaptive DB optimization skill path          |
| `15-ui-optimization-governance.mdc`| ✅        | —                               | **Mandatory automatic UI/frontend optimization** |
| `02-ui-ux.mdc`                   | ❌          | resources/js/**                 | Framework-independent UX                     |
| `react-inertia.mdc`              | ❌          | resources/js/**                 | React/Inertia implementation                 |
| `03-window-system.mdc`           | ❌          | resources/js/**                 | Window/dialog governance                     |
| `04-responsive-adaptive.mdc`     | ❌          | resources/js/**                 | Responsive + adaptive                        |
| `08-api-governance.mdc`          | ❌          | routes/**, app/Http/**          | API contracts                                |
| `09-testing-governance.mdc`      | ❌          | tests/**                        | Test requirements                            |
| `10-MODULES.mdc`                 | ❌          | app/**, routes, pages, docs/sis | Module boundaries                            |
| `12-DOCUMENTATION.mdc`           | ❌          | broad                           | Doc update requirements                      |
| `13-runtime-platform.mdc`        | ❌          | resources/js/**                 | Runtime/platform rules                       |
| `14-framework-adapters.mdc`      | ❌          | resources/js/**                 | Adapter boundaries                           |
| `16-desktop-ui-governance.mdc`   | ❌          | `clients/sis-desktop/**`        | **Professional desktop UI (Blazor Hybrid — ADR required)** |

---

## Constitution § → Artifact Mapping

| Constitution §     | Topic                                   | Primary artifact                                                                         |
| ------------------ | --------------------------------------- | ---------------------------------------------------------------------------------------- |
| 0–2, 61–63, 81–83  | Core directive, workflow, change report | `00-SIS-CONSTITUTION.mdc`, `SIS-CONSTITUTION.md`                                         |
| 3–5, 24–25         | Architecture / business authority       | `01-ARCHITECTURE.mdc`, `sis-core.mdc`, `clean-architecture.mdc`, `ARCHITECTURE-STACK.md` |
| 6–7, 19–22         | UI contracts, design system             | `UI-CONTRACT.md`, `02-ui-ux.mdc`, `react-inertia.mdc`                                    |
| 11                 | Windows desktop experience              | `DESKTOP-UI-GOVERNANCE.md`, `16-desktop-ui-governance.mdc`, `WINDOW-CONTRACT.md`         |
| 15–18              | Window / dialog                         | `WINDOW-CONTRACT.md`, `03-window-system.mdc`                                             |
| 14, 45–47          | Responsive + adaptive                   | `04-responsive-adaptive.mdc`, `PLATFORM-CONTRACT.md`                                     |
| 26–27, 30–31       | Security                                | `security.mdc`, `SECURITY-BASELINE.json`, `docs/security/`                               |
| 28                 | Database                                | `database-changes-mandatory.mdc`, `database-blueprint.md`                                |
| 29                 | API                                     | `api-conventions.md`, `08-api-governance.mdc`                                            |
| 50                 | Testing                                 | `testing-strategy.md`, `09-testing-governance.mdc`                                       |
| 53–55              | Modules                                 | `10-MODULES.mdc`, `MODULE-CONTRACT.md`, `WORK-PLAN.md`                                   |
| 58–60, 78–80       | Change control, documentation           | `11-change-control.mdc`, `12-DOCUMENTATION.mdc`                                          |
| 9–10, 40–45, 71–73 | Runtime / platform                      | `RUNTIME-CONTRACT.md`, `13-runtime-platform.mdc`, `14-framework-adapters.mdc`            |
| 65                 | Architecture baseline                   | `ARCHITECTURE-BASELINE.md`                                                               |
| Performance        | Adaptive DB                             | `DATABASE-ADAPTIVE-GOVERNANCE.md`, `autonomous-optimization.mdc`                         |
| Performance        | UI / frontend automatic optimization    | `UI-OPTIMIZATION-GOVERNANCE.md`, `15-ui-optimization-governance.mdc`                   |
| Desktop UI         | Professional Blazor Hybrid adapter        | `DESKTOP-UI-GOVERNANCE.md`, `16-desktop-ui-governance.mdc` (FUTURE / ADR)              |

---

## Canonical Sources (Authoritative)

| Domain              | Authoritative source                                                   |
| ------------------- | ---------------------------------------------------------------------- |
| Constitution        | `.cursor/architecture/SIS-CONSTITUTION.md` + `00-SIS-CONSTITUTION.mdc` |
| Architecture layers | `ARCHITECTURE-STACK.md`, `01-ARCHITECTURE.mdc`                         |
| Security baseline   | `.cursor/security/SECURITY-BASELINE.json`                              |
| Database schema     | `database-blueprint.md` (89 tables)                                    |
| API                 | `api-conventions.md`                                                   |
| UI                  | `UI-CONTRACT.md` + `02-ui-ux.mdc`                                      |
| Window              | `WINDOW-CONTRACT.md`                                                   |
| Responsive/Platform | `PLATFORM-CONTRACT.md` + `04-responsive-adaptive.mdc`                  |
| Runtime             | `RUNTIME-CONTRACT.md`                                                  |
| Framework adapters  | `14-framework-adapters.mdc`                                            |
| Modules             | `MODULE-CONTRACT.md` + `10-MODULES.mdc`                                |
| Testing             | `testing-strategy.md` + `09-testing-governance.mdc`                    |
| Documentation       | `12-DOCUMENTATION.mdc`                                                 |
| Change control      | `11-change-control.mdc`                                                |

---

## Agent Entry Points

1. `AGENTS.md`
2. `.cursor/brain/PROJECT.md`
3. `.cursor/architecture/SIS-CONSTITUTION.md` (full constitution v2.0)
4. `.cursor/architecture/GOVERNANCE-MAP.md` (this file)
5. `.cursor/architecture/README.md` (index)

---

## Automation vs Documentation

| Control                         | Enforcement                                                              |
| ------------------------------- | ------------------------------------------------------------------------ |
| Clean Architecture layers       | `architecture:validate --fitness`, `architecture:graph`, CI              |
| Feature contracts               | `architecture:feature-check {Context}`                                   |
| Security baseline               | `security:validate`, SecurityArchitectureValidator, security tests       |
| Database changes                | database-change skill, migrations, blueprint updates                     |
| Constitution workflow           | `alwaysApply` rules **00, 01, sis-core, 15, 11** (see Always Applied table) |
| Module boundaries               | `10-MODULES.mdc` + MODULE-CONTRACT (documented + review)                 |
| UI architecture drift           | **MISSING** — planned future validator (Phase 0.2 deferred)              |
| Frontend framework introduction | ADR + human gate (documented)                                            |
| Path traversal                  | `security.mdc` guidance + secure coding review; not standalone validator |

---

## Bootstrap / Hardening Timeline

| Date       | Event                                                                           |
| ---------- | ------------------------------------------------------------------------------- |
| 2026-09-08 | Constitution v2.0 bootstrap (`4f9dd01`)                                         |
| 2026-09-08 | Phase 0.1 audit — PASS WITH CONDITIONS, 78/100                                  |
| 2026-09-08 | Phase 0.2 governance hardening — see `docs/governance/PHASE-0.2-GATE-REPORT.md` |
