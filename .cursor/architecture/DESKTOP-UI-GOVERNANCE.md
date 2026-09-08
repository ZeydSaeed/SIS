# SIS Professional Desktop UI Governance

**Version:** 1.0  
**Status:** FUTURE / ADR REQUIRED — normative target for approved Blazor Hybrid desktop adapter  
**Date:** 2026-09-08  
**Index rule:** `.cursor/rules/16-desktop-ui-governance.mdc`  
**Constitution:** §11 (Windows Desktop Experience), §15–18 (Window/Dialog), §40–44 (Framework adapters)

## Scope and precedence

| Runtime | Status | Governance |
|---------|--------|------------|
| `web-inertia` (React + Inertia) | **ACTIVE** | `UI-CONTRACT.md`, `react-inertia.mdc`, `15-ui-optimization-governance.mdc` |
| `desktop-blazor-hybrid` (C# / .NET / Blazor) | **FUTURE — ADR required** | **This document** |

**This document does NOT authorize introducing Blazor, .NET, or a desktop host without ADR + human approval** (`11-change-control.mdc`, `13-runtime-platform.mdc`, `RUNTIME-CONTRACT.md`).

**Precedence:** Constitution → Architecture → Security → Database → API → Modules → UI/UX → `15-ui-optimization-governance` → **this document (desktop adapter only)** → framework-specific rules.

Laravel remains authoritative for business rules, authorization, and data operations. Blazor/C# owns desktop presentation, UI state, workspace management, and user interaction only.

---

## 1. Mission

Build SIS as a professional desktop-class application, not as a conventional web dashboard.

The target UX quality is comparable in interaction model and structural sophistication to professional applications such as Microsoft Word, Excel, Visual Studio, Adobe Photoshop and other enterprise desktop applications.

The goal is NOT to copy their visual identity.

The goal is to adopt their proven desktop interaction patterns:

* Application Shell
* Ribbon / Command Bar
* Docking Windows
* Tabbed Workspace
* Tool Windows
* Context Menus
* Command Palette
* Keyboard Shortcuts
* Professional Data Grids
* Persistent Workspace Layout
* Multi-window / Multi-monitor support
* High-DPI support
* Theming
* Accessibility
* Performance-oriented rendering

---

## 2. Mandatory Technology Architecture

### Backend

* Laravel
* PHP
* PostgreSQL
* Redis where required
* REST/JSON API
* HTTPS

### Desktop

* C#
* .NET
* Blazor Hybrid
* Razor Components

### UI

* Razor
* HTML
* CSS
* JavaScript only when required for browser/DOM interoperability

The desktop UI MUST NOT move backend business rules into JavaScript.

---

## 3. Architectural Separation

The system MUST maintain the following separation:

```text
Desktop Host
        ↓
Blazor UI
        ↓
Application/UI Services
        ↓
Laravel API
        ↓
Business Rules
        ↓
PostgreSQL
```

Laravel remains responsible for authoritative backend business rules, authorization and data operations.

Blazor/C# remains responsible for desktop presentation, UI state, navigation, workspace management, local UI services and user interaction.

Never duplicate authoritative business rules between the UI and Laravel.

---

## 4. Application Shell

SIS MUST have one centralized Application Shell.

The Shell MUST provide:

* Application header
* Main menu
* Ribbon or command bar
* Navigation
* Workspace
* Docking infrastructure
* Tab management
* Notifications
* Status bar
* Global search
* Command palette

Modules MUST NOT create independent application shells.

---

## 5. Workspace

All major SIS modules MUST operate inside the central Workspace.

Examples:

* Student
* Enrollment
* Academic
* Finance
* Courses
* Reports
* Administration

The Workspace MUST support where applicable:

* Tabs
* Close
* Reopen
* Pin
* Reorder
* Refresh
* Duplicate
* Active-tab state
* Persistent layout
* Multi-pane views

Do not open ordinary application workflows as uncontrolled browser-like pages.

---

## 6. Docking

Where the workflow benefits from multiple information panes, use Dockable Panels.

Panels may support:

* Dock
* Float
* Resize
* Collapse
* Reorder
* Restore

Examples:

* Student Explorer
* Properties
* Filters
* Details
* Notifications
* Related Records
* Navigation

Do not implement custom one-off panel behavior when a standardized docking service/component exists.

---

## 7. Command Architecture

Every meaningful user action MUST be represented as a Command.

Examples:

* Student.Create
* Student.Edit
* Student.Delete
* Student.Print
* Student.Export
* Enrollment.Create
* Enrollment.Cancel
* Enrollment.UpdatePlacement

UI controls MUST invoke Commands rather than embedding business logic.

Commands MAY be triggered by:

* Ribbon
* Toolbar
* Button
* Context Menu
* Keyboard Shortcut
* Command Palette

The same Command MUST represent the same logical action regardless of invocation source.

---

## 8. Command Registry

Maintain a centralized Command Registry.

Every command SHOULD define:

* commandId
* title
* category
* icon
* shortcut
* permission requirement
* availability state
* execution handler
* confirmation requirement where applicable

Do not create duplicate command implementations.

---

## 9. Component Registry

Before creating a new UI component, inspect the SIS Component Registry.

Reuse existing components whenever possible.

Standard components SHOULD include:

* SISButton
* SISInput
* SISSelect
* SISDataGrid
* SISTab
* SISDialog
* SISForm
* SISPanel
* SISToolbar
* SISSearchBox
* SISNotification
* SISDatePicker
* SISWizard
* SISPropertyGrid
* SISCommandButton

If no suitable component exists, determine whether the missing capability should become a reusable platform component before implementing it locally.

---

## 10. Data Grid Standard

Every enterprise grid MUST support the features required by its workflow.

Where applicable:

* Sorting
* Filtering
* Multi-column sorting
* Column resizing
* Column reordering
* Column visibility
* Selection
* Multi-selection
* Keyboard navigation
* Copy
* Export
* Print
* Context menu
* Server-side paging
* Server-side filtering
* Server-side sorting
* Virtualization for large datasets

Never load unnecessarily large datasets into the client.

---

## 11. Form Standard

Forms MUST use the SIS Form System.

Supported patterns include:

* Create
* Edit
* View
* Search
* Wizard
* Settings

Forms SHOULD contain standardized:

* Header
* Toolbar
* Sections
* Fields
* Validation
* Actions
* Footer/status area

Do not create arbitrary form layouts when an existing SIS pattern applies.

---

## 12. Dialog Standard

Use the centralized Dialog Service.

Supported dialog categories:

* Information
* Confirmation
* Warning
* Error
* Input
* Selection
* Advanced
* Wizard

Dialogs MUST have predictable keyboard behavior.

ESC SHOULD cancel/close where safe.

ENTER SHOULD confirm where safe.

Destructive operations MUST require appropriate confirmation according to their risk.

---

## 13. Context Menu Standard

Context menus MUST be generated from the applicable Command Registry.

Do not duplicate command logic inside context menus.

Context menus SHOULD expose actions relevant to the selected object and current permissions.

---

## 14. Command Palette

SIS SHOULD provide a global Command Palette for professional keyboard-oriented users.

The Command Palette MUST search registered commands rather than hard-coded page actions.

Commands MUST respect authorization and current availability state.

---

## 15. Keyboard First

Important workflows MUST be usable with the keyboard.

Support appropriate shortcuts for:

* New
* Save
* Search
* Print
* Refresh
* Undo/Redo where supported
* Navigation
* Dialog confirmation
* Workspace navigation

Avoid shortcut collisions.

All shortcuts MUST be centrally registered.

---

## 16. Multi-Monitor

The desktop application SHOULD support multiple monitors where technically appropriate.

Floating windows and panels MUST remain recoverable if monitor configuration changes.

The application MUST NOT permanently lose windows outside the visible desktop.

---

## 17. High DPI

All UI MUST support Windows scaling including:

* 100%
* 125%
* 150%
* 175%
* 200%

The implementation MUST avoid:

* clipped text
* clipped controls
* overlapping elements
* unreadable icons
* dialogs outside the visible screen

Never use fixed pixel dimensions where a scalable layout is appropriate.

---

## 18. Responsive Desktop Layout

Responsive design MUST consider desktop window sizes.

The UI MUST adapt between:

* Compact
* Standard
* Large
* Ultra-wide

Responsive behavior MUST preserve usability rather than simply shrinking controls.

---

## 19. Design System

All UI MUST use the SIS Design System.

The Design System MUST define:

* Typography
* Colors
* Spacing
* Icons
* Borders
* Radii
* Elevation
* Focus states
* Disabled states
* Error states
* Warning states
* Success states
* Loading states

Do not scatter arbitrary styling values throughout the application.

Use centralized design tokens.

---

## 20. Theming

The UI MUST support a centralized theme architecture.

At minimum prepare the architecture for:

* Light
* Dark
* High Contrast

Themes MUST be implemented through design tokens and semantic variables.

Do not hard-code colors throughout individual components.

---

## 21. Accessibility

All new UI MUST consider:

* Keyboard navigation
* Focus management
* Accessible labels
* Semantic structure
* Contrast
* Error identification
* Screen-reader compatibility where applicable

Never communicate important state using color alone.

---

## 22. Loading and Error States

Every asynchronous operation MUST have an explicit state model.

At minimum consider:

* Idle
* Loading
* Success
* Empty
* Error
* Offline
* Retry

Do not leave users staring at an unresponsive interface.

Avoid blocking the entire application for operations that can be localized to a component or workspace.

---

## 23. Connection State

The desktop application MUST detect and communicate:

* Connected
* Connecting
* Offline
* Server unavailable

Network failures MUST be handled gracefully.

Do not assume that every API request succeeds.

---

## 24. Security

UI visibility is NOT authorization.

Hiding a button does NOT constitute security.

All sensitive operations MUST be authorized by Laravel/backend policies and permissions.

The backend remains authoritative.

The UI MUST reflect permissions but MUST NOT be trusted as the security boundary.

---

## 25. Performance

Every new screen MUST consider:

* Rendering cost
* API request count
* Payload size
* Virtualization
* Lazy loading
* Server-side filtering
* Server-side pagination
* Caching where appropriate
* Avoiding unnecessary re-rendering

Large enterprise datasets MUST NOT be rendered naively.

When the desktop adapter is active, `15-ui-optimization-governance.mdc` still applies — evaluate optimization; do not apply aggressive techniques without evidence.

---

## 26. Reusability

Before implementing a feature, determine:

1. Does a component already exist?
2. Does a service already exist?
3. Does a command already exist?
4. Does a workspace pattern already exist?
5. Does a form/dialog pattern already exist?
6. Can the new capability be reused by another SIS module?

Prefer platform-level reusable solutions over local one-off implementations.

---

## 27. No UI Fragmentation

The following are prohibited without explicit architectural justification:

* Random sidebar designs
* Random toolbar designs
* Random button styles
* Random dialogs
* Random grid implementations
* Random navigation systems
* Random spacing systems
* Random color systems
* Module-specific shells
* Duplicate commands
* Duplicate components
* Duplicate notification systems

Consistency is a mandatory architectural requirement.

---

## 28. Cross-Platform Preparation

The UI architecture MUST avoid unnecessary coupling to Windows-specific APIs.

Use abstraction services for capabilities such as:

* Window management
* Clipboard
* File operations
* Printing
* Notifications
* Workspace persistence
* Dialogs

Windows-specific implementations MAY exist behind interfaces.

This allows future Web/Tablet implementations without redesigning the entire application.

---

## 29. Laravel Integration

The UI MUST communicate with Laravel through defined APIs.

Do not directly access PostgreSQL from the Blazor UI.

Do not bypass Laravel authorization.

Do not duplicate backend business rules inside UI components.

Use typed DTOs/contracts where appropriate.

Align command IDs and API contracts with `UI-CONTRACT.md` and `RUNTIME-CONTRACT.md`.

---

## 30. UI Development Workflow

For every new screen or major UI modification:

1. Identify the module.
2. Identify the applicable Workspace.
3. Identify existing components.
4. Identify existing Commands.
5. Identify required permissions.
6. Identify API contracts.
7. Select the correct UI pattern.
8. Implement using SIS Design System.
9. Integrate with Shell/Workspace.
10. Implement loading/empty/error/offline states.
11. Validate keyboard behavior.
12. Validate DPI/scaling.
13. Validate accessibility.
14. Validate performance.
15. Validate visual consistency.
16. Run automated tests.
17. Run UI/parity checks.
18. Produce an implementation report.

---

## 31. Mandatory Pre-Implementation Questions

Before writing code, Cursor MUST determine:

* Is this a new screen or an extension of an existing screen?
* Which Workspace owns it?
* Which existing components can be reused?
* Which Commands are involved?
* Which permissions are required?
* Which API endpoints/contracts are required?
* Is docking appropriate?
* Is a tab appropriate?
* Is a dialog appropriate?
* Is a wizard appropriate?
* Is a DataGrid appropriate?
* What happens during loading?
* What happens when there is no data?
* What happens when the API fails?
* What happens offline?
* What keyboard shortcuts are required?
* What happens at 100–200% DPI?
* Does this introduce a duplicate pattern?

Do not ask the user questions that can be answered by inspecting the existing codebase and governance documentation.

---

## 32. Architecture Violation Rule

If an implementation violates an existing SIS UI Governance rule:

DO NOT silently bypass the rule.

Instead:

1. Identify the violation.
2. Explain the reason.
3. Determine whether an existing architecture can satisfy the requirement.
4. If genuinely impossible, document an exception.
5. Never weaken a global rule merely to simplify implementation.

---

## 33. Definition of Done

A UI feature is NOT complete merely because it renders.

It is complete only when:

* Functional behavior works.
* API integration works.
* Authorization works.
* Shell integration works.
* Workspace integration works.
* Design System is followed.
* Existing components are reused.
* Commands are centralized.
* Loading/empty/error states exist.
* Keyboard interaction works where applicable.
* DPI behavior is acceptable.
* Accessibility requirements are considered.
* Performance is acceptable.
* Tests pass.
* No architecture violations remain.
* Documentation/report is updated.

---

## 34. Golden Rule

ALWAYS prefer:

```text
Existing Shell
        >
Existing Workspace
        >
Existing Component
        >
Existing Command
        >
Existing Service
        >
Existing Design Pattern
        >
New reusable component
        >
New local implementation
```

Never start from scratch when the SIS architecture already provides the required capability.

---

## 35. Final Principle

SIS MUST behave as a professional application platform.

A new module must feel like a native part of SIS, not like a separate website embedded inside SIS.

Visual consistency, interaction consistency, command consistency, keyboard behavior, workspace behavior, accessibility, performance and architectural consistency are mandatory quality attributes.

---

## Related contracts

| Contract | Relationship |
|----------|--------------|
| `WINDOW-CONTRACT.md` | Logical window/dialog IDs and Window Manager target |
| `UI-CONTRACT.md` | Shared command names, permissions, API semantics |
| `RUNTIME-CONTRACT.md` | Runtime registry — `desktop-blazor-hybrid` entry |
| `PLATFORM-CONTRACT.md` | Responsive/adaptive profiles |
| `UI-OPTIMIZATION-GOVERNANCE.md` | Mandatory optimization evaluation on UI changes |
