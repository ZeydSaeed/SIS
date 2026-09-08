# SIS — Permanent Engineering Constitution

**Version:** 2.0  
**Project:** SIS  
**Status:** PERMANENT / MANDATORY  
**Installed:** 2026-09-08  
**Index rule:** `.cursor/rules/00-SIS-CONSTITUTION.mdc`  
**Governance map:** `.cursor/architecture/GOVERNANCE-MAP.md`

This document is the **Permanent Engineering Constitution of SIS**. Rules in `.cursor/rules/` and `.cursor/architecture/` are mandatory — not suggestions. Compliance is automatic on every task.

---

## 0. Absolute Directive

Operating inside SIS, every task must automatically apply all relevant rules without the user repeating architecture, security, UI, or platform requirements.

---

## 1. Primary Objective

Deliver features while preserving: security, data integrity, architecture, business rules, API contracts, authorization, UI contracts, platform adaptability, responsive design, accessibility, testing, maintainability, performance.

---

## 2. Absolute Priority Order

```text
1. Security
2. Data integrity
3. Tenant isolation
4. Authorization
5. Existing architecture
6. Business/domain integrity
7. API contract integrity
8. SIS Constitution
9. Module boundaries
10. UI consistency
11. Platform adaptation
12. Responsive behavior
13. Accessibility
14. Testability
15. Maintainability
16. Performance
17. Developer convenience
```

Never sacrifice a higher priority for a lower one.

---

## 3–5. Technology Baseline & Architecture

- **Current stack:** Laravel 13, Inertia/React, PostgreSQL, Redis — see `ARCHITECTURE-BASELINE.md`
- Do not replace technologies without inspect → evaluate → impact → ADR → approval
- Separate: Business Logic · Application · API · UI Contracts · Presentation · Platform Adaptation · Runtime Hosting
- No presentation technology owns business rules

---

## 6–7. Framework-Independent Contracts & UI Runtime

Contracts: Window, Dialog, Form, Grid, Navigation, Toolbar, Tab, Workspace, Responsive, Accessibility, Command, Notification, Auth, API, State.

```text
SIS UI Contract → UI Runtime Adapter → Presentation Framework → Platform Host
```

---

## 8–10. Platform Detection & Framework Resolution

- Capability-based detection (viewport, pointer, touch, keyboard) — not fragile user-agent business logic
- Framework selection: OS + capabilities + runtime + configured UI — not OS alone

---

## 11–14. Desktop / Tablet / Mobile / Responsive + Adaptive

- Windows desktop: multi-window experience when Window Manager exists (target)
- Tablet: split view, tabs, adaptive panels
- Mobile: pages, drawers, bottom sheets — not scaled desktop
- Both responsive (layout) and adaptive (interaction model) required

---

## 15–18. Window Manager & Dialogs

- Centralized window management — no per-module window systems
- Stable logical window IDs (`student.details`, `enrollment.create`)
- Dialogs for short workflows; large workflows use pages/windows
- See `WINDOW-CONTRACT.md`

---

## 19–22. Component-First & Design System

- Page → Layout → Toolbar → Form/Grid → Dialog → Actions
- Reuse before create; CSS variables (`--sis-*`) → Tailwind → components → pages

---

## 23–25. Business/UI Separation & Laravel Architecture

```text
UI → API → Application Handler → Domain → DB
```

- One authoritative business rule implementation
- Thin controllers; handlers own transactions
- Frontend validation = UX; backend = authoritative

---

## 26–27. Authorization & Multi-Tenancy

- Server-side auth on every protected operation
- School/tenant isolation — never frontend-only filtering

---

## 28–29. Database & API Governance

- Migrations only; inspect before destructive changes
- Stable API contracts; search consumers before breaking changes
- See `api-conventions.md`, `database-changes-mandatory.mdc`

---

## 30–31. Security & Auditability

- OWASP-class risks on every feature
- Auditable sensitive operations; never log secrets
- See `security.mdc`, Phase 3.10.1 baseline

---

## 32–37. Forms, Grids, States, Navigation, Commands, Accessibility

- Full UI states: idle, loading, success, empty, error, disabled, retry
- Server pagination; keyboard + a11y mandatory
- Command palette compatible architecture (implement when requested)

---

## 38–39. RTL / i18n & Device Capability

- Arabic + English; RTL first-class
- **Forbidden:** platform-specific business logic (`if iPhone`, `if React`)

---

## 40–45. Platform Adapters & Presentation Profiles

- Adapters isolate presentation only
- Profiles: desktop, tablet, mobile, touch, keyboard, mixed

---

## 46–48. Same Feature / Multiple Presentations & PWA

- One logical contract; presentations differ; business rules shared
- Offline-ready architecture without bypassing auth

---

## 49–50. Performance & Testing

- No N+1, no unbounded loads; measure before optimize
- Tests required by change type — see `testing-strategy.md`

---

## 51–52. Architecture Drift & Dependencies

- Detect duplication, UI business logic, auth bypass
- Evaluate dependencies before adding libraries

---

## 53–55. New Module / Page / Feature

- Follow `MODULE-CONTRACT.md`, `FEATURE-DONE.md`, `WORK-PLAN.md`
- Module order: organization → academic → security → students → enrollment → …

---

## 56–60. Modify / Extend / Delete / Refactor / No Shortcuts

- Smallest safe change; search before delete
- Record unrelated debt as `TECHNICAL DEBT`
- No security bypasses or unexplained hacks

---

## 61. Required Task Workflow

```text
UNDERSTAND → INSPECT → IMPACT ANALYSIS → PLAN → IMPLEMENT → TEST → VALIDATE → REPORT
```

---

## 62–63. Safe Autonomy & STOP Conditions

- Classify: LOW / MEDIUM / HIGH / CRITICAL
- STOP for: destructive DB, auth bypass, breaking API, data loss, tenant risk, new framework without ADR

---

## 64–65. Existing Functionality & Architecture Baseline

- Repository is baseline — do not invent architecture
- Document technical debt; do not silent rewrite

---

## 66–67. Governance File Structure & Rule Hierarchy

```text
00-SIS-CONSTITUTION → architecture/security → UI/runtime → coding/testing
```

See `GOVERNANCE-MAP.md` for mapping to existing rules.

---

## 68–69. Governance Bootstrap

- Inspect repo first; governance-only changes during bootstrap
- Application code unchanged unless required for safe install

---

## 70–73. Drift Automation, Technology Introduction, Multi-Framework, Runtime Registry

- ADR + approval for React/Vue/Blazor/Electron introduction
- Multiple UI frameworks only through shared contracts — see `RUNTIME-CONTRACT.md`

---

## 74–77. Capability Matrix, Windows Dev, Cross-Platform, Platform Consistency

- Windows desktop primary dev baseline for window UX
- Same business meaning across platforms

---

## 78–80. Change Control, Technical Debt, Documentation

- Breaking changes need approval and migration plan
- ADRs for significant patterns

---

## 81. Required Completion Report

After significant tasks, return **SIS CHANGE REPORT**:

```text
Status · Risk · Summary · Changed/Added/Removed · Database · API · UI · Runtime · Platform
Responsive (Desktop/Tablet/Mobile) · Security · Authorization · Tests · Validation · Regression · Technical Debt · Next Step
```

---

## 82. Definition of Done

Requirement + architecture + security + authorization + API + DB + responsive/adaptive + accessibility + tests + validation + no regression + docs when required.

---

## 83. Final Permanent Rule

```text
ONE SIS + ONE BUSINESS MODEL + ONE AUTHORIZATION + ONE DATA MODEL + ONE API
+ ONE DESIGN SYSTEM + ONE UI CONTRACT + MULTIPLE PRESENTATIONS + MULTIPLE RUNTIMES
```

Business logic, security, authorization, API, and data rules remain shared. UI adapts; architecture must not fragment.

---

**END OF SIS PERMANENT ENGINEERING CONSTITUTION v2.0**
