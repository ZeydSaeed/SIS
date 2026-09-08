# SIS — PERMANENT ENGINEERING CONSTITUTION

# MULTI-PLATFORM ARCHITECTURE

# UI RUNTIME GOVERNANCE

# AUTONOMOUS DEVELOPMENT RULES

**Version:** 2.0
**Project:** SIS
**Status:** PERMANENT / MANDATORY
**Purpose:** Permanent engineering governance and multi-platform UI architecture

**Installed:** 2026-09-08 (canonical v2.0 — recovered from Phase 0.1 source)
**Index rule:** .cursor/rules/00-SIS-CONSTITUTION.mdc
**Governance map:** .cursor/architecture/GOVERNANCE-MAP.md

---

---

# 0. ABSOLUTE DIRECTIVE

You are operating inside the SIS project.

This document is the **Permanent Engineering Constitution of SIS**.

The rules contained in:

```text
.cursor/rules/
.cursor/architecture/
```

and this Constitution are mandatory engineering constraints.

They are NOT suggestions.

They are NOT optional.

They MUST automatically apply to every future task.

The user must NOT be required to repeat:

```text
follow the architecture
follow the rules
follow responsive rules
follow security rules
follow UI standards
follow platform rules
```

Compliance is automatic.

Whenever the user gives a task, even a very short task such as:

```text
Add a student window.
Modify registration.
Add a form.
Add a module.
Change this page.
Add a report.
Fix this bug.
Delete this dialog.
Add a new workflow.
Extend this screen.
```

you MUST automatically determine and apply every relevant SIS rule.

---

# 1. PRIMARY OBJECTIVE

The objective is NOT merely:

> Make the requested feature work.

The objective is:

> Make the requested feature work while preserving the long-term architectural integrity, security, portability, scalability, maintainability, usability, and platform independence of SIS.

Every implementation MUST therefore preserve:

```text
Security
+
Data Integrity
+
Architecture
+
Business Rules
+
API Contracts
+
Authorization
+
UI Contracts
+
Platform Adaptability
+
Responsive Design
+
Accessibility
+
Testing
+
Maintainability
+
Performance
```

---

# 2. ABSOLUTE PRIORITY ORDER

For every task use this priority:

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

Never sacrifice a higher-priority item for a lower-priority item.

---

# 3. CURRENT TECHNOLOGY BASELINE

The existing SIS application MUST be treated as the current implementation baseline.

Current core technologies may include:

```text
Laravel
PHP
JavaScript
HTML
CSS
Tailwind CSS
PostgreSQL
MySQL
```

depending on the actual repository configuration.

DO NOT replace existing technologies simply because another framework is theoretically better.

Before introducing or changing technology:

```text
Inspect
→
Evaluate
→
Impact Analysis
→
Architecture Decision
→
Approval if required
→
Implement
```

---

# 4. MULTI-RUNTIME TECHNOLOGY STRATEGY

SIS MUST be architected so that its business and application logic are not permanently coupled to one frontend framework.

The architecture MUST be capable of supporting, where justified:

```text
HTML/CSS/JavaScript
React
Vue
Blazor
ASP.NET Core
Electron
Tauri
PWA
Web Browser
Desktop Hosts
Mobile Hosts
```

However:

## IMPORTANT

The existence of these technologies in the architecture does NOT mean that all of them must be installed or used simultaneously.

Do NOT introduce a framework merely for theoretical compatibility.

A framework may be introduced only when there is an actual architectural requirement and the repository supports it.

---

# 5. CORE ARCHITECTURAL PRINCIPLE

The SIS architecture MUST separate:

```text
Business Logic
Application Logic
API Contracts
UI Contracts
Presentation
Platform Adaptation
Runtime Hosting
```

Conceptually:

```text
                    SIS PLATFORM
                         │
        ┌────────────────┴────────────────┐
        │                                 │
   Application Core                  API Contracts
        │                                 │
        └────────────────┬────────────────┘
                         │
                  SIS UI Contracts
                         │
        ┌────────────────┼─────────────────┐
        │                │                 │
     Web UI         Desktop UI        Mobile UI
        │                │                 │
   JS/React/Vue    Electron/Tauri    PWA/Web
   Blazor/Web      Desktop Host      Mobile Host
        │                │                 │
        └────────────────┼─────────────────┘
                         │
                Adaptive Presentation
```

No presentation technology may become the owner of the business rules.

---

# 6. FRAMEWORK-INDEPENDENT CONTRACTS

The following contracts MUST remain framework-independent:

```text
Window Contract
Dialog Contract
Form Contract
Grid Contract
Navigation Contract
Toolbar Contract
Tab Contract
Workspace Contract
Responsive Contract
Accessibility Contract
Command Contract
Notification Contract
Authentication Contract
Authorization Contract
API Contract
State Contract
```

Contracts MUST NOT unnecessarily depend on:

```text
React
Vue
Blazor
ASP.NET
Electron
Laravel
PHP
```

Framework-specific implementation belongs behind adapters.

---

# 7. UI RUNTIME LAYER

The UI architecture MUST conceptually follow:

```text
SIS UI Contract
        ↓
UI Runtime Adapter
        ↓
Presentation Framework
        ↓
Platform Host
```

Possible implementations include:

```text
SIS UI Contract
      ↓
JavaScript Adapter
      ↓
HTML/CSS/JS

SIS UI Contract
      ↓
React Adapter
      ↓
React

SIS UI Contract
      ↓
Vue Adapter
      ↓
Vue

SIS UI Contract
      ↓
Blazor Adapter
      ↓
Blazor

SIS UI Contract
      ↓
Desktop Adapter
      ↓
Electron / Tauri / native host
```

Do not duplicate business logic between adapters.

---

# 8. PLATFORM DETECTION

SIS MUST support runtime adaptation according to:

```text
Device
Operating System
Browser
Viewport
Orientation
Input capability
Touch capability
Keyboard availability
Pointer type
Screen size
Display density
Network state
PWA state
Desktop host
Mobile host
Available runtime
```

DO NOT rely exclusively on user-agent detection.

DO NOT build fragile logic such as:

```text
if iPhone
if Android
if Windows
```

when capability detection can be used.

Prefer:

```text
viewport
pointer
touch
keyboard
orientation
screen capability
runtime capability
host capability
```

---

# 9. PLATFORM RESOLUTION ENGINE

SIS SHOULD use a centralized platform/runtime resolution mechanism.

Conceptually:

```text
Platform Detection
        ↓
Capability Detection
        ↓
Runtime Detection
        ↓
Presentation Profile
        ↓
UI Adapter
        ↓
Component
```

The resolver may determine:

```text
platform = desktop / tablet / mobile
os = windows / macos / linux / ios / android
input = mouse / touch / keyboard / mixed
runtime = web / pwa / desktop-host / mobile-web
framework = js / react / vue / blazor
presentation = desktop-window / adaptive / mobile
```

The resolver MUST NOT contain business logic.

---

# 10. FRAMEWORK RESOLUTION RULE

Framework selection MUST NOT be based solely on operating system.

For example:

```text
Windows ≠ automatically Blazor
Android ≠ automatically React
iOS ≠ automatically Vue
```

Instead evaluate:

```text
Operating System
+
Device capabilities
+
Available runtime
+
Installed/hosted application
+
Configured UI implementation
+
Project architecture
```

The selected presentation MUST consume the same:

```text
business rules
API
authorization
validation
commands
data contracts
audit rules
```

---

# 11. WINDOWS DESKTOP EXPERIENCE

The current development environment is Windows.

Therefore, when running SIS on a Windows desktop/laptop environment, the preferred presentation SHOULD provide a Windows-like application experience.

Where supported:

```text
Windows
├── Windows
├── Multiple Windows
├── Move
├── Resize
├── Minimize
├── Maximize
├── Restore
├── Close
├── Dock
├── Undock
├── Z-index
├── Tabs
├── Workspaces
├── Context Menus
├── Keyboard Shortcuts
└── Multiple Open Forms
```

Example:

```text
Student
Registration
Course
Exam
Report
```

may be open simultaneously.

Do NOT force a single-page mobile-style interaction model onto desktop users.

---

# 12. TABLET EXPERIENCE

Tablet presentation MUST adapt according to available space and input capabilities.

Possible presentation:

```text
Split View
Tabs
Adaptive Windows
Resizable Panels
Collapsible Navigation
Touch Controls
```

Do not simply scale desktop windows down.

---

# 13. MOBILE EXPERIENCE

Mobile MUST NOT be a scaled-down desktop.

Transform presentation when appropriate:

```text
Desktop Window
→
Mobile Page

Desktop Dialog
→
Full-Screen Dialog / Bottom Sheet

Multiple Windows
→
Tabs / Navigation Stack

Sidebar
→
Drawer

Toolbar
→
Compact Action Bar

Context Menu
→
Action Sheet / More Menu
```

The business behavior remains the same.

Only the presentation changes.

---

# 14. RESPONSIVE + ADAPTIVE UI

SIS MUST implement BOTH:

```text
Responsive Design
AND
Adaptive UI
```

Responsive controls:

```text
dimensions
spacing
layout
columns
typography
grid
container sizes
```

Adaptive controls:

```text
navigation
windows
dialogs
menus
toolbars
data presentation
interaction model
input method
```

---

# 15. WINDOW MANAGER

Window management MUST be centralized.

Do NOT implement independent window systems inside individual modules.

The Window Manager MUST provide a stable logical API similar to:

```text
open()
close()
minimize()
maximize()
restore()
move()
resize()
focus()
bringToFront()
dock()
undock()
activate()
persist()
restoreState()
```

Every window MUST have a stable logical identity.

Examples:

```text
student.details
student.edit
student.registration
registration.create
registration.edit
course.details
report.transcript
```

Never rely only on random DOM IDs.

---

# 16. WINDOW CONTRACT

Every desktop-capable window MUST conceptually define:

```text
windowId
title
component/page
size
minimumSize
resizable
movable
maximizable
minimizable
closable
modal
parent
permissions
route
state
```

Example:

```json
{
    "windowId": "student.details",
    "title": "Student Details",
    "resizable": true,
    "movable": true,
    "maximizable": true,
    "minimizable": true,
    "closable": true
}
```

The actual implementation may differ according to the existing project architecture.

---

# 17. WINDOW STATE

Where appropriate persist:

```text
windowId
position
size
state
workspace
tab group
dock state
```

Persistence may be:

```text
local
server-side
database-backed
```

depending on requirements.

Never persist secrets or sensitive information as window state.

---

# 18. DIALOG SYSTEM

Dialogs MUST be centralized.

Use dialogs for:

```text
confirmation
short editing
focused workflows
warnings
destructive actions
contextual information
```

Do not put large workflows into tiny dialogs.

Presentation may adapt:

```text
Desktop → Dialog
Tablet → Adaptive Dialog
Mobile → Fullscreen / Bottom Sheet
```

---

# 19. COMPONENT-FIRST ARCHITECTURE

Prefer:

```text
Page
 ├── Layout
 ├── Toolbar
 ├── Form
 ├── Grid
 ├── Dialog
 ├── Validation
 └── Actions
```

Avoid giant monolithic pages.

Before creating a component:

```text
Search existing components
↓
Search existing patterns
↓
Extend if appropriate
↓
Create reusable component
↓
Only then create feature-specific UI
```

---

# 20. DESIGN SYSTEM

All supported UI runtimes MUST consume the SIS Design System.

The Design System defines:

```text
colors
typography
spacing
borders
radius
shadows
icons
buttons
inputs
forms
dialogs
windows
tables
navigation
states
```

Use CSS variables as the foundational token layer.

Examples:

```text
--sis-primary
--sis-secondary
--sis-background
--sis-surface
--sis-border
--sis-text
--sis-muted
--sis-danger
--sis-success
--sis-warning
--sis-spacing-*
--sis-radius-*
--sis-shadow-*
```

Tailwind MAY consume these tokens.

Do NOT create random visual values repeatedly.

---

# 21. TAILWIND GOVERNANCE

Tailwind is a styling implementation tool.

It is NOT the architecture.

Preferred hierarchy:

```text
SIS Design Tokens
        ↓
Tailwind
        ↓
Reusable SIS Components
        ↓
Pages
```

Avoid uncontrolled utility duplication.

---

# 22. WEB COMPONENT COMPATIBILITY

Core UI concepts SHOULD remain compatible with Web Component principles where practical.

Potential components:

```text
sis-window
sis-dialog
sis-grid
sis-form
sis-tabs
sis-toolbar
sis-menu
sis-workspace
```

Do NOT implement Web Components solely for theoretical reasons.

The objective is framework independence.

---

# 23. BUSINESS/UI SEPARATION

Critical business rules MUST NOT live in:

```text
HTML
CSS
JavaScript UI
React components
Vue components
Blazor components
Electron
```

Preferred:

```text
UI
 ↓
API/Application Boundary
 ↓
Laravel Application Layer
 ↓
Domain/Business Rules
 ↓
Database
```

The same business operation MUST be usable by:

```text
Web
PWA
Desktop
Mobile
React
Vue
Blazor
Future clients
```

---

# 24. LARAVEL ARCHITECTURE

Respect the existing Laravel architecture.

Use appropriate separation:

```text
Controllers
Requests
Models
Policies
Services
Actions
Events
Listeners
Jobs
Resources
DTOs
Repositories only when justified
```

Controllers MUST remain thin.

Do not place substantial business logic into controllers.

---

# 25. BUSINESS RULE AUTHORITY

Critical business rules MUST have one authoritative implementation.

Never duplicate critical business logic between:

```text
JavaScript
Controllers
Models
Views
React
Vue
Blazor
Mobile
Desktop
```

Frontend validation may improve UX.

Backend validation and business rules remain authoritative.

---

# 26. AUTHORIZATION

Every protected operation MUST consider:

```text
Authentication
Authorization
Role
Permission
Ownership
Tenant
Module access
Record-level access
```

Hiding a button is NOT authorization.

Authorization MUST be enforced server-side.

All UI runtimes must respect the same authorization contract.

---

# 27. MULTI-TENANCY

SIS MUST remain SaaS-ready.

Where applicable enforce:

```text
tenant_id
tenant isolation
authorization isolation
configuration isolation
storage isolation
cache isolation
job isolation
```

Never allow cross-tenant access.

Never rely solely on frontend tenant filtering.

---

# 28. DATABASE GOVERNANCE

Every structural database change MUST use migrations.

Before destructive changes inspect:

```text
records
foreign keys
indexes
constraints
relations
consumers
reports
APIs
jobs
```

Never silently destroy production data.

---

# 29. API GOVERNANCE

API contracts MUST remain stable.

Before modifying an API:

```text
Search all consumers
```

Check:

```text
Web
PWA
Desktop
Mobile
React
Vue
Blazor
Reports
Jobs
Integrations
External clients
```

Prefer backward-compatible evolution.

---

# 30. SECURITY GOVERNANCE

Every feature MUST consider:

```text
SQL Injection
XSS
CSRF
Mass Assignment
IDOR
Authorization Bypass
File Upload Attacks
Path Traversal
Session Security
Sensitive Data Exposure
Rate Limiting
Audit Logging
Input Validation
Output Encoding
Secret Handling
```

Never trust client input.

Never expose:

```text
stack traces
SQL errors
internal paths
secrets
tokens
credentials
```

to normal users.

---

# 31. AUDITABILITY

Important operations SHOULD be auditable.

Examples:

```text
Create
Update
Delete
Approve
Reject
Enroll
Withdraw
Publish
Import
Export
Permission changes
```

Audit information should identify:

```text
who
what
when
where/context
result
```

Never log:

```text
passwords
tokens
secrets
credentials
```

---

# 32. FORMS

Every form MUST consider:

```text
Purpose
Fields
Validation
Permissions
Submit action
Cancel behavior
Error handling
Loading state
Success behavior
Responsive behavior
Keyboard behavior
Accessibility
Audit requirement
```

Use both:

```text
Client-side UX validation
+
Server-side authoritative validation
```

---

# 33. DATA GRIDS

Every data grid MUST consider:

```text
sorting
filtering
searching
pagination
column visibility
loading
empty state
error state
keyboard navigation
responsive behavior
touch behavior
```

Do not load thousands of records unnecessarily.

Mobile may use:

```text
cards
stacked records
horizontal scrolling
priority columns
adaptive details
```

instead of forcing a desktop table.

---

# 34. UI STATES

Important asynchronous operations MUST support:

```text
Idle
Loading
Success
Empty
Error
Disabled
Retry
```

Never allow the UI to appear frozen during network operations.

---

# 35. NAVIGATION

Navigation MUST be centralized where practical.

Menus, toolbars, keyboard shortcuts, mobile actions and contextual actions SHOULD use common commands/actions.

Avoid duplicated navigation logic.

---

# 36. COMMAND SYSTEM

Where appropriate:

```text
UI
 ↓
Command
 ↓
Application Action
 ↓
Backend
```

Examples:

```text
STUDENT.CREATE
STUDENT.EDIT
STUDENT.DELETE
REGISTRATION.CREATE
REGISTRATION.APPROVE
REPORT.TRANSCRIPT
```

Commands may be triggered by:

```text
menu
toolbar
keyboard
context menu
mobile action
command palette
```

The architecture MUST remain compatible with:

```text
Ctrl + K
```

command palette behavior.

Do not implement it unless requested.

---

# 37. ACCESSIBILITY

Every UI implementation MUST consider:

```text
keyboard navigation
focus management
semantic HTML
labels
ARIA
contrast
screen readers
visible focus
touch targets
```

Accessibility is mandatory.

---

# 38. RTL / INTERNATIONALIZATION

SIS MUST support localization.

At minimum prepare for:

```text
Arabic
English
```

where project configuration requires it.

RTL MUST be first-class.

RTL must cover:

```text
navigation
forms
tables
dialogs
menus
icons
spacing
alignment
keyboard interaction
```

Do not implement RTL by randomly reversing CSS properties.

---

# 39. DEVICE CAPABILITY GOVERNANCE

Never create architecture such as:

```text
if iPhone:
    business logic A

if Android:
    business logic B

if React:
    business logic C

if Vue:
    business logic D

if Blazor:
    business logic E
```

This is FORBIDDEN.

Platform-specific behavior belongs in:

```text
Presentation
Adapters
Runtime
Host
```

Business logic remains shared.

---

# 40. PLATFORM ADAPTERS

Platform-specific behavior MUST be isolated behind adapters.

Conceptually:

```text
SIS Core
   │
   ├── Web Adapter
   ├── PWA Adapter
   ├── Desktop Adapter
   ├── Mobile Adapter
   ├── React Adapter
   ├── Vue Adapter
   └── Blazor Adapter
```

Only introduce an adapter when required.

---

# 41. ELECTRON GOVERNANCE

Electron is NOT part of SIS Core.

Electron MUST NEVER own:

```text
Business Logic
Authorization
Database Rules
Window Contract
Form Contract
Navigation Contract
Design System
```

Electron may act as:

```text
SIS Web UI
     ↓
Electron Desktop Host
```

The desktop host MUST remain replaceable.

The architecture SHOULD permit:

```text
Electron
Tauri
Native Host
Browser
PWA
```

without rewriting SIS business logic.

---

# 42. BLAZOR / ASP.NET GOVERNANCE

Blazor and ASP.NET Core MAY be supported as future or parallel implementations.

However:

```text
Do not introduce them without architectural justification.
```

If introduced:

```text
Blazor
    ↓
SIS UI Contracts
    ↓
SIS API/Application Contracts
```

Blazor MUST NOT become the owner of business rules that belong to SIS Core.

ASP.NET Core MUST NOT be introduced merely because it is compatible with Blazor.

---

# 43. REACT GOVERNANCE

React MAY be used as a UI adapter where justified.

React MUST consume:

```text
SIS UI Contracts
SIS API Contracts
SIS Authorization
SIS Design System
SIS Commands
```

React MUST NOT create a parallel business architecture.

---

# 44. VUE GOVERNANCE

Vue MAY be used where justified.

Vue MUST consume the same:

```text
API
Business Contracts
Authorization
UI Contracts
Design Tokens
Commands
```

Do not create a separate business implementation.

---

# 45. RUNTIME SELECTION

When the application starts, the UI runtime SHOULD determine:

```text
Device Category
Operating System
Viewport
Orientation
Touch capability
Pointer capability
Keyboard capability
Network capability
PWA state
Desktop host
Available UI runtime
```

Then select an appropriate:

```text
Presentation Profile
```

Example:

```text
Windows Desktop
→ Desktop Presentation
→ Windows-like Window Manager

Windows Laptop
→ Desktop Presentation

Tablet
→ Adaptive Presentation

Android Phone
→ Mobile Presentation

iPhone
→ Mobile Presentation

iPad
→ Tablet Presentation

Browser
→ Web Presentation

PWA
→ PWA Presentation
```

The exact framework selection MUST follow the configured runtime architecture.

---

# 46. PRESENTATION PROFILES

Use logical presentation profiles rather than hard-coding device-specific layouts.

Examples:

```text
desktop
tablet
mobile
compact
touch
keyboard
mouse
mixed
```

A presentation profile may determine:

```text
window behavior
navigation
dialog behavior
toolbar density
grid presentation
sidebar behavior
input behavior
```

---

# 47. SAME FEATURE / MULTIPLE PRESENTATIONS

A feature MUST have one logical contract.

Example:

```text
StudentForm
```

may have:

```text
Desktop Presentation
Tablet Presentation
Mobile Presentation
```

All must reuse:

```text
validation
authorization
API
business rules
data contract
commands
audit
```

Only presentation differs.

---

# 48. OFFLINE / PWA READINESS

Architecture MUST remain compatible with:

```text
PWA
Caching
Offline-aware UX
Retry
Reconnect
Installable behavior
```

Do not assume permanent network connectivity.

Offline support MUST NOT bypass authorization or security.

---

# 49. PERFORMANCE

Every feature MUST consider:

```text
database queries
N+1 queries
payload size
bundle size
rendering cost
repeated API calls
caching
pagination
lazy loading
memory usage
```

Do not load unnecessary data.

Do not create heavy runtime abstractions without justification.

---

# 50. TESTING

New functionality MUST include appropriate testing.

Consider:

```text
Unit Tests
Feature Tests
Integration Tests
Authorization Tests
API Tests
Regression Tests
UI Tests
Responsive Tests
Accessibility Tests
```

The appropriate test level depends on the feature.

A feature is NOT complete merely because it visually works.

---

# 51. ARCHITECTURE DRIFT PREVENTION

The project MUST continuously detect or document:

```text
duplicated components
duplicated window systems
unauthorized database access
business logic in UI
business logic in controllers
authorization bypass
duplicated validation
global JavaScript
random CSS systems
unauthorized dependencies
cross-module coupling
framework-specific business logic
platform-specific business logic
```

Where practical create automated checks.

---

# 52. DEPENDENCY GOVERNANCE

Before introducing a dependency:

Evaluate:

```text
purpose
security
license
maintenance
bundle impact
performance
compatibility
duplication
existing alternatives
```

Do not introduce a library merely because it is convenient.

---

# 53. NEW MODULE

Every new module MUST define:

```text
Module
 ├── Business Responsibility
 ├── Routes
 ├── Controllers
 ├── Requests
 ├── Services/Actions
 ├── Policies
 ├── Models
 ├── UI
 ├── Components
 ├── JavaScript
 ├── API
 ├── Tests
 └── Documentation
```

Follow the actual existing SIS structure.

Do not create arbitrary structures.

---

# 54. NEW PAGE

Every new page MUST answer:

```text
Which module owns it?
Which route owns it?
Which permission protects it?
What data does it consume?
Which components does it reuse?
What is its desktop presentation?
What is its tablet presentation?
What is its mobile presentation?
What is its loading state?
What is its empty state?
What is its error state?
What tests cover it?
```

---

# 55. NEW FEATURE

Every feature MUST consider:

```text
Business Purpose
User Flow
UI
Backend
Database
API
Authorization
Validation
Audit
Testing
Responsive Behavior
Adaptive Behavior
Accessibility
Failure Behavior
Recovery
Rollback where applicable
```

---

# 56. MODIFY EXISTING WINDOW

When modifying an existing window:

FIRST inspect:

```text
Current structure
Current behavior
Current dependencies
Window Contract
Responsive behavior
Permissions
Tests
```

Then perform the smallest safe change.

Do NOT rebuild the window unnecessarily.

---

# 57. EXTEND EXISTING WINDOW

Prefer:

```text
Existing Window
      ↓
Existing Components
      ↓
New Reusable Component
      ↓
Minimal Integration
```

Do not duplicate the entire window.

---

# 58. DELETE GOVERNANCE

Deletion is HIGH RISK.

Before deleting anything:

```text
Search repository
Search imports
Search routes
Search APIs
Search JavaScript
Search tests
Search documentation
Search database usage
Search permissions
Search configuration
```

If deletion is destructive or irreversible:

```text
STOP
```

and request approval.

---

# 59. REFACTORING

Do NOT refactor unrelated code during a feature task unless required.

If unrelated technical debt is discovered:

```text
TECHNICAL DEBT
```

Record it.

Do not silently expand scope.

---

# 60. NO MAGIC SHORTCUTS

Forbidden:

```text
random global variables
hidden DOM state
hard-coded IDs
duplicated logic
unexplained hacks
security bypasses
authorization bypasses
suppressed errors
ignored tests
framework-specific business logic
platform-specific business logic
```

If a workaround is necessary document:

```text
WHY
RISK
SCOPE
ALTERNATIVE
FUTURE REPLACEMENT
```

---

# 61. REQUIRED TASK WORKFLOW

Every task MUST follow:

```text
UNDERSTAND
    ↓
INSPECT
    ↓
IMPACT ANALYSIS
    ↓
PLAN
    ↓
IMPLEMENT
    ↓
TEST
    ↓
VALIDATE
    ↓
REPORT
```

## UNDERSTAND

Determine:

```text
User requirement
Module
Files
Database
API
UI
Dependencies
Existing behavior
```

## INSPECT

Read relevant:

```text
.cursor/rules
architecture documentation
existing implementation
components
tests
migrations
routes
services
policies
JavaScript
CSS
API contracts
```

## IMPACT ANALYSIS

Evaluate:

```text
UI
Backend
Database
API
Security
Authorization
Performance
Responsive
Accessibility
Testing
Regression
Migration
Platform
Runtime
```

## PLAN

Create a concise internal implementation plan.

## IMPLEMENT

Only after inspection and impact analysis.

## VALIDATE

Run appropriate:

```text
Syntax
Static Analysis
Tests
Feature Tests
Authorization Tests
API Tests
UI validation
Responsive validation
Accessibility validation
Security validation
Database validation
Regression validation
```

---

# 62. SAFE AUTONOMY

Classify every significant task:

```text
LOW
MEDIUM
HIGH
CRITICAL
```

## LOW

Examples:

```text
spacing
label
CSS
non-breaking UI
minor visual change
```

## MEDIUM

Examples:

```text
new component
new page
new endpoint
new form
```

## HIGH

Examples:

```text
database structure
authorization
major workflow
API contract
cross-module change
runtime architecture
framework introduction
```

## CRITICAL

Examples:

```text
data deletion
tenant isolation
security boundary
production migration
irreversible change
```

---

# 63. STOP CONDITIONS

STOP and request clarification/approval when:

```text
1. Requirements contradict existing architecture.
2. Destructive production database changes are required.
3. Security boundaries must be weakened.
4. Authorization must be bypassed.
5. Critical existing functionality must be removed.
6. Breaking API changes are unavoidable.
7. Data loss is possible.
8. Requirements conflict with mandatory SIS rules.
9. Correct behavior cannot be determined safely.
10. Multiple interpretations materially change implementation.
11. A new framework/runtime would materially alter architecture.
12. Tenant isolation could be affected.
13. A platform adapter would require duplicated business logic.
```

Do not guess in high-risk situations.

---

# 64. EXISTING FUNCTIONALITY PROTECTION

Existing working functionality is protected.

DO NOT:

```text
rewrite working modules unnecessarily
replace architecture without justification
remove functionality
rename public contracts without migration
change database structures casually
remove routes without impact analysis
remove APIs without checking consumers
delete components because they appear unused
replace libraries without justification
```

---

# 65. ARCHITECTURE BASELINE

The repository itself is the primary baseline.

Do NOT invent architecture.

Before establishing:

```text
ARCHITECTURE-BASELINE.md
UI-CONTRACT.md
WINDOW-CONTRACT.md
MODULE-CONTRACT.md
RUNTIME-CONTRACT.md
PLATFORM-CONTRACT.md
```

inspect the actual repository.

If technical debt exists:

```text
document it
```

Do not silently rewrite the application.

---

# 66. GOVERNANCE FILE STRUCTURE

Maintain:

```text
.cursor/
├── rules/
│   ├── 00-SIS-CONSTITUTION.mdc
│   ├── 01-ARCHITECTURE.mdc
│   ├── 02-UI-UX.mdc
│   ├── 03-WINDOW-SYSTEM.mdc
│   ├── 04-RESPONSIVE-ADAPTIVE.mdc
│   ├── 05-CODING-STANDARDS.mdc
│   ├── 06-SECURITY.mdc
│   ├── 07-DATABASE.mdc
│   ├── 08-API.mdc
│   ├── 09-TESTING.mdc
│   ├── 10-MODULES.mdc
│   ├── 11-CHANGE-CONTROL.mdc
│   ├── 12-DOCUMENTATION.mdc
│   ├── 13-RUNTIME-PLATFORM.mdc
│   └── 14-FRAMEWORK-ADAPTERS.mdc
│
└── architecture/
    ├── ARCHITECTURE-BASELINE.md
    ├── UI-CONTRACT.md
    ├── WINDOW-CONTRACT.md
    ├── MODULE-CONTRACT.md
    ├── RUNTIME-CONTRACT.md
    └── PLATFORM-CONTRACT.md
```

If equivalent files already exist:

```text
DO NOT DUPLICATE THEM.
```

Merge carefully.

---

# 67. RULE HIERARCHY

The hierarchy is:

```text
00-SIS-CONSTITUTION
        ↓
01-ARCHITECTURE
        ↓
06-SECURITY
        ↓
07-DATABASE / 08-API
        ↓
13-RUNTIME-PLATFORM
        ↓
14-FRAMEWORK-ADAPTERS
        ↓
02-UI
03-WINDOW
04-RESPONSIVE
        ↓
05-CODING
09-TESTING
10-MODULES
11-CHANGE
12-DOCUMENTATION
```

Higher-priority rules override lower-priority convenience.

---

# 68. GOVERNANCE BOOTSTRAP

When this Constitution is first installed:

## STEP 1

Inspect:

```text
Laravel version
PHP version
database
frontend
JavaScript
CSS
Tailwind
routes
controllers
models
services
policies
migrations
tests
layouts
components
dialogs
forms
navigation
modules
architecture documentation
existing Cursor rules
```

## STEP 2

Create/update:

```text
.cursor/rules/
.cursor/architecture/
```

## STEP 3

Document actual architecture.

## STEP 4

Document UI contracts.

## STEP 5

Document Window Manager contract.

## STEP 6

Document Module contract.

## STEP 7

Document Runtime and Platform contracts.

## STEP 8

Establish architecture drift checks.

## STEP 9

Validate governance.

---

# 69. BOOTSTRAP SAFETY

During governance installation:

DO NOT modify application functionality unless absolutely required to install governance safely.

The bootstrap objective is:

```text
Governance first
```

not:

```text
Application rewrite
```

Existing application code should remain unchanged.

---

# 70. ARCHITECTURE DRIFT AUTOMATION

Where practical, establish automated checks that detect:

```text
unauthorized dependencies
business logic in UI
direct unauthorized database access
authorization bypass patterns
duplicated window systems
duplicated notification systems
duplicated modal systems
global JavaScript
uncontrolled CSS
framework-specific business logic
platform-specific business logic
cross-module violations
```

Automated checks MUST complement—not replace—architectural review.

---

# 71. TECHNOLOGY INTRODUCTION RULE

Before introducing:

```text
React
Vue
Blazor
ASP.NET Core
Electron
Tauri
another frontend framework
another backend framework
```

perform:

```text
Purpose
Benefits
Alternatives
Architecture impact
Security impact
Performance impact
Maintenance impact
Bundle/deployment impact
Existing-code impact
Migration impact
```

If HIGH/CRITICAL:

```text
STOP
REQUEST APPROVAL
```

---

# 72. MULTI-FRAMEWORK COEXISTENCE

If multiple UI technologies exist in SIS, they MUST coexist through stable contracts.

Correct:

```text
                 SIS Core
                    │
               API / Contracts
                    │
        ┌───────────┼────────────┐
        │           │            │
      React       Vue         Blazor
        │           │            │
        └───────────┼────────────┘
                    │
             Platform Adapter
                    │
        ┌───────────┼────────────┐
        │           │            │
      Browser     PWA        Desktop Host
```

Incorrect:

```text
React → business logic A
Vue → business logic B
Blazor → business logic C
Electron → business logic D
```

---

# 73. UI RUNTIME REGISTRY

Where multiple runtimes exist, maintain a centralized registry describing:

```text
runtime
framework
version
supported platforms
supported presentation profiles
entry point
capabilities
status
```

Example conceptual structure:

```text
runtime:
    id: blazor
    framework: Blazor
    supported:
        desktop: true
        tablet: true
        mobile: true
```

The actual schema MUST follow repository architecture.

Do not invent duplicate runtime registries.

---

# 74. CAPABILITY MATRIX

The architecture SHOULD maintain a capability matrix:

```text
                Desktop  Tablet  Mobile  Touch  Keyboard
Windowing          ✓        ✓       -       ✓       ✓
Resize             ✓        ✓       -       ✓       ✓
Multiple Windows   ✓        ✓       -       -       ✓
Drawer             ✓        ✓       ✓       ✓       ✓
Bottom Sheet       -        ✓       ✓       ✓       -
```

This is conceptual.

Actual capabilities MUST be determined by the runtime and repository implementation.

---

# 75. WINDOWS DEVELOPMENT ENVIRONMENT

The current development machine is Windows.

Therefore, when testing desktop presentation:

```text
Windows Desktop
```

is the primary baseline.

Desktop validation MUST verify:

```text
window opening
moving
resizing
minimize
maximize
restore
close
focus
z-index
multiple windows
keyboard interaction
mouse interaction
```

Do not assume that mobile presentation is sufficient.

---

# 76. CROSS-PLATFORM VALIDATION

When a feature affects presentation, validate as applicable:

```text
Windows Desktop
Windows Laptop
Tablet
Android
iPhone
iPad
Web Browser
PWA
```

Validation does NOT mean every developer must physically test every platform.

Use appropriate:

```text
automated tests
browser testing
responsive testing
device emulation
CI
integration testing
```

when physical hardware is unavailable.

---

# 77. PLATFORM CONSISTENCY

Different platforms MAY have different presentation.

They MUST NOT have different business meaning.

Example:

Desktop:

```text
Student Window
```

Mobile:

```text
Student Page
```

Both represent the same:

```text
Student
Permissions
Validation
API
Business rules
Commands
Audit
```

---

# 78. CHANGE CONTROL

Before modifying an existing public contract:

```text
Search consumers
Evaluate compatibility
Plan migration
Test
Document
```

Breaking changes require approval.

---

# 79. TECHNICAL DEBT

If implementation reveals existing technical debt:

```text
TECHNICAL DEBT
```

must be recorded.

Do not silently solve unrelated technical debt.

---

# 80. DOCUMENTATION

Architectural decisions MUST be documented.

Use ADRs when introducing significant architectural patterns.

Examples:

```text
ADR-001 Window Manager
ADR-002 Workspace Manager
ADR-003 Responsive Strategy
ADR-004 Adaptive Platform Strategy
ADR-005 Component Architecture
ADR-006 SaaS/Tenancy
ADR-007 UI Runtime Architecture
ADR-008 Framework Adapter Strategy
ADR-009 Desktop Host Strategy
```

Only create ADRs when appropriate.

---

# 81. REQUIRED COMPLETION REPORT

After every completed task return:

```text
## SIS CHANGE REPORT

Status:
PASS / PASS WITH CONDITIONS / BLOCKED

Risk:
LOW / MEDIUM / HIGH / CRITICAL

Summary:
...

Changed:
- ...

Added:
- ...

Removed:
- ...

Database:
- NONE / DETAILS

API:
- NONE / DETAILS

UI:
- DETAILS

Runtime:
- DETAILS

Platform:
- DETAILS

Responsive:
- Desktop:
- Tablet:
- Mobile:

Framework:
- Current:
- Adapter affected:
- NONE if not applicable

Security:
- ...

Authorization:
- ...

Tests:
- ...

Validation:
- ...

Regression:
- LOW / MEDIUM / HIGH

Technical Debt:
- ...

Remaining Issues:
- ...

Recommended Next Step:
- ...
```

---

# 82. DEFINITION OF DONE

A task is complete ONLY when:

```text
Requirement implemented
+
Architecture compliant
+
Security reviewed
+
Authorization reviewed
+
API reviewed
+
Database reviewed
+
Responsive reviewed
+
Adaptive behavior reviewed
+
Accessibility considered
+
Tests added/updated where appropriate
+
Validation completed
+
No unexplained regression
+
Documentation updated when required
+
Platform impact evaluated
+
Runtime impact evaluated
```

---

# 83. FINAL PERMANENT RULE

SIS MUST evolve as one coherent platform.

Every:

```text
Page
Form
Window
Dialog
Module
Feature
API
Service
Database Change
Component
Runtime
Framework Adapter
Platform Adapter
Desktop Host
Mobile Presentation
```

MUST integrate into the existing architecture.

Never create a parallel architecture merely to solve a local problem.

The final objective is:

```text
ONE SIS
+
ONE BUSINESS MODEL
+
ONE AUTHORIZATION MODEL
+
ONE DATA MODEL
+
ONE API CONTRACT
+
ONE DESIGN SYSTEM
+
ONE UI CONTRACT
+
MULTIPLE PRESENTATIONS
+
MULTIPLE RUNTIMES
+
MULTIPLE PLATFORMS
```

Conceptually:

```text
                         SIS
                          │
                ┌─────────┴─────────┐
                │                   │
          Business Core         API Contracts
                │                   │
                └─────────┬─────────┘
                          │
                    UI Contracts
                          │
          ┌───────────────┼────────────────┐
          │               │                │
       Web UI         Desktop UI       Mobile UI
          │               │                │
    JS/React/Vue      Electron/Tauri    PWA/Web
    /Blazor/Web       /Desktop Host     /Mobile
          │               │                │
          └───────────────┼────────────────┘
                          │
                 Platform Resolver
                          │
          ┌───────────────┼────────────────┐
          │               │                │
       Desktop          Tablet           Mobile
          │               │                │
      Windows UI      Adaptive UI       Mobile UI
```

The platform, device, operating system, capabilities, runtime and configured UI implementation determine the presentation.

Business logic MUST remain shared.

Security MUST remain shared.

Authorization MUST remain shared.

API contracts MUST remain shared.

Data rules MUST remain shared.

The UI may adapt.

The architecture must not fragment.

END OF SIS PERMANENT ENGINEERING CONSTITUTION
