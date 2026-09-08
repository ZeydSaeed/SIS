# SIS — Mandatory Automatic Optimization Governance

## Universal Automatic Optimization Enforcement

**Version:** 1.0  
**Status:** MANDATORY / ALWAYS APPLY  
**Scope:** Entire SIS Project  
**Purpose:** Automatically optimize every new or modified page, window, dialog, form, table, component, dashboard, layout, navigation surface, API/UI boundary, and frontend asset without requiring the developer to explicitly request optimization.

**Index rule:** `.cursor/rules/15-ui-optimization-governance.mdc`  
**Related (distinct):** `autonomous-optimization.mdc` — adaptive **database** optimization; `query-optimization.mdc` — query performance in `app/**`.

---

# 0. ABSOLUTE RULE

Optimization is NOT an optional task.

Optimization is NOT a separate phase that must be manually requested.

Optimization is a mandatory part of implementation.

Whenever Cursor:

* creates a page
* modifies a page
* creates a window
* modifies a window
* creates a Dialog
* modifies a Dialog
* creates a form
* modifies a form
* creates a table
* modifies a table
* creates a dashboard
* modifies a dashboard
* creates a component
* modifies a component
* creates navigation
* modifies navigation
* creates a responsive layout
* modifies responsive behavior
* adds a new UI interaction
* adds data loading
* adds filtering
* adds searching
* adds pagination
* adds charts
* adds images
* adds animations
* adds API consumption
* adds server-side data
* adds state
* adds modal/sheet/drawer behavior

Cursor MUST automatically evaluate and apply the relevant optimization rules.

The developer MUST NOT need to say:

> optimize this page

or:

> improve performance

or:

> make this responsive

or:

> optimize the dialog

Optimization happens automatically.

---

# 1. GOVERNANCE PRECEDENCE

This Optimization Constitution is subordinate to the SIS Constitution and Architecture Governance.

The precedence order is:

1. `00-SIS-CONSTITUTION.mdc`
2. `01-ARCHITECTURE.mdc`
3. `sis-core.mdc`
4. Clean Architecture / Architecture Governance
5. Security Governance
6. Database Governance
7. API Governance
8. Module Governance
9. UI/UX Governance
10. Runtime / Platform Governance
11. **THIS OPTIMIZATION GOVERNANCE**
12. Framework-specific implementation rules
13. Local implementation preferences

Optimization MUST NEVER:

* violate security
* violate authorization
* violate tenant/school isolation
* duplicate business logic
* bypass Application handlers
* bypass Domain rules
* bypass API contracts
* introduce framework coupling into SIS Core
* introduce unnecessary dependencies
* change behavior merely to improve performance
* reduce accessibility
* break RTL/LTR
* break responsive behavior
* destroy visual parity
* create architecture drift

Correctness ALWAYS has priority over performance.

---

# 2. AUTOMATIC OPTIMIZATION PIPELINE

Every UI change MUST conceptually pass through:

```text
Detect Change
      ↓
Classify Surface
      ↓
Determine Optimization Requirements
      ↓
Apply Safe Optimizations
      ↓
Check Architecture
      ↓
Check Security
      ↓
Check Accessibility
      ↓
Check Responsive Behavior
      ↓
Check Rendering Cost
      ↓
Check Network/Data Cost
      ↓
Check Memory/Lifecycle
      ↓
Check Bundle/Asset Cost
      ↓
Run Relevant Validation
      ↓
Optimization Gate
      ↓
Implementation Complete
```

Cursor MUST NOT skip the optimization evaluation because the user did not explicitly request it.

---

# 3. OPTIMIZATION PRINCIPLE

Use:

```text
Measure → Identify → Optimize → Validate → Compare
```

Never:

```text
Guess → Rewrite → Add Libraries
```

Do NOT perform speculative optimization that adds complexity without evidence.

Prefer:

* simple
* measurable
* reversible
* framework-native
* dependency-free
* architecture-safe
* maintainable

optimizations.

---

# 4. UNIVERSAL UI OPTIMIZATION RULES

Every UI surface MUST be evaluated for:

* DOM size
* component count
* rendering frequency
* unnecessary re-rendering
* data volume
* network requests
* asset size
* CSS complexity
* JavaScript execution
* memory lifecycle
* accessibility
* responsive layout
* keyboard behavior
* RTL/LTR
* loading states
* error states
* empty states

---

# 5. DOM OPTIMIZATION

Cursor MUST avoid unnecessarily large DOM trees.

Do NOT create excessive nesting when semantic or simpler structure is possible.

Prefer:

* semantic HTML
* fewer wrappers
* reusable components
* CSS layout instead of structural wrapper abuse

Avoid rendering elements that are not required.

For large collections:

```text
Pagination
→ Server filtering
→ Server sorting
→ Virtualization when justified
```

Do NOT render thousands of records unnecessarily.

---

# 6. HTML OPTIMIZATION

Prefer semantic HTML: `main`, `section`, `header`, `nav`, `article`, `form`, `fieldset`, `label`, `button`, `table`, `thead`, `tbody`, `th`, `td`.

Avoid replacing semantic elements with generic `<div>` elements.

Every interactive control MUST have correct semantic element, accessible name, keyboard support, and focus behavior.

Do not use JavaScript where native HTML can perform the task.

---

# 7. CSS OPTIMIZATION

Evaluate: layout cost, paint cost, compositing, selector complexity, duplication, responsive behavior, RTL compatibility, maintainability.

Prefer `transform` and `opacity` for animations where appropriate.

Avoid expensive animations involving `top`, `left`, `width`, `height`, `margin` when transform/opacity can safely achieve the same result.

Use `content-visibility: auto` only when it does not interfere with accessibility, layout, measurement, or required rendering behavior.

---

# 8. CSS DESIGN SYSTEM OPTIMIZATION

Do NOT introduce duplicated styling systems. Use existing SIS design tokens (CSS variables, Tailwind tokens, shared components, logical CSS properties).

Do NOT create a second design system.

---

# 9. RTL/LTR OPTIMIZATION

All new UI MUST support both RTL and LTR.

Use logical properties: `margin-inline`, `padding-inline`, `inset-inline`, `border-inline`, `text-align: start/end`.

Avoid unnecessary physical `margin-left/right`, `padding-left/right`, `left/right` when logical properties apply.

Icons and directional controls MUST be evaluated for RTL inversion.

---

# 10. RESPONSIVE OPTIMIZATION

Every new page/component MUST be evaluated against Desktop, Tablet, and Mobile.

The UI MUST NOT merely shrink — it must adapt.

Preferred transformation:

```text
Desktop → Window / Table / Toolbar
Tablet  → Expanded Surface / Compact Table
Mobile  → Page / Cards / Sheet / Drawer
```

Do NOT create separate business logic for each viewport.

---

# 11. PAGE OPTIMIZATION

Evaluate: initial payload, lazy loading, code splitting, navigation.

Use approved Inertia navigation. Do NOT introduce fetch, axios, React Query, Redux, or Zustand unless separately approved.

---

# 12. REACT OPTIMIZATION

Evaluate: unnecessary renders, component boundaries, state location, prop identity, expensive calculations, large lists, lazy loading, code splitting.

Use `React.memo`, `useMemo`, `useCallback`, `React.lazy` only when structurally justified — not mechanically on every component.

---

# 13. REACT STATE OPTIMIZATION

Priority: Server State → URL State → Local Component State → Shared State → Global State.

Do NOT promote local UI state into global state without justification. Avoid duplicated server state.

---

# 14–17. FUTURE ADAPTERS

Vue, Blazor, ASP.NET Core, Electron optimizations apply only when those adapters are **architecture-approved**. They remain UI/platform adapters — no SIS business rules in adapter-specific code.

Until approved: do not introduce those stacks for optimization.

---

# 18. LARAVEL/PHP OPTIMIZATION

Evaluate: N+1, eager loading, query count, selected columns, pagination, cursor pagination, caching, queue usage, serialization.

Never cache sensitive data across users or schools. Caching must respect invalidation, authorization, tenant isolation, PII, and consistency.

---

# 19. DATABASE OPTIMIZATION

Evaluate indexes, filtering, sorting, joins, N+1, unnecessary columns, pagination, cardinality.

Prefer server-side filtering/sorting/pagination. Use UI virtualization for rendering problems — not as a substitute for database pagination.

---

# 20. TABLE OPTIMIZATION

| Data volume | Approach |
|-------------|----------|
| Small | Normal rendering |
| Medium | Server pagination/filtering/sorting |
| Large | Consider virtualization |
| Very large | Server pagination + indexed search + column selection + virtualization |

Never render unnecessary thousands of rows.

---

# 21. SEARCH OPTIMIZATION

Search MUST NOT issue unnecessary requests per keystroke. Use debounce → request → server-side indexed search where appropriate.

Do not implement client-side filtering of huge datasets.

---

# 22. FORM OPTIMIZATION

Forms MUST avoid unnecessary renders, preserve server-side authority, show errors efficiently, avoid duplicate submissions, maintain keyboard accessibility and focus.

Use Inertia `<Form>`, Wayfinder, FormRequest patterns. Do NOT introduce another form state library unless approved.

---

# 23. DIALOG OPTIMIZATION

Evaluate lazy mounting, lifecycle, focus trap, focus restoration, ESC, keyboard navigation, scrolling, memory, responsive transformation.

Prefer: closed → lightweight; opened → mount required content; closed → cleanup.

---

# 24. WINDOW OPTIMIZATION

Future Window Manager is centralized — do NOT invent local window-management systems in modules until officially implemented.

---

# 25. DASHBOARD OPTIMIZATION

Dashboards MUST NOT perform uncontrolled request explosions.

Prefer aggregated Application Query/DTO when multiple widgets can be efficiently served together. Expensive widgets MAY be lazy-loaded.

Each widget: evaluate initial cost, refresh cost, data volume, rendering cost, error isolation.

---

# 26–28. ASSET OPTIMIZATION

Images: dimensions, compression, format, lazy loading, thumbnails, responsive sizes.

Fonts: minimize families/weights; prefer WOFF2; preserve Arabic and English support.

JavaScript: avoid unnecessary loops, duplicate listeners, large synchronous work; clean up listeners and timers.

---

# 29. MEMORY OPTIMIZATION

Check event listener leaks, timers, subscriptions, retained objects, abandoned requests. Use framework-appropriate cleanup (`useEffect` cleanup in React, etc.).

---

# 30–31. NETWORK & PAYLOAD OPTIMIZATION

Evaluate request count, payload size, duplicate requests, compression, lazy loading, prefetching (only with strong UX justification).

APIs and Inertia props return only required data via DTOs. Security filtering server-side before serialization. Optimization MUST NEVER expose additional PII.

---

# 32–34. NON-NEGOTIABLES

**Accessibility:** MUST NOT remove labels, semantic HTML, keyboard nav, focus management, or error announcements.

**Visual parity:** MUST NOT arbitrarily change spacing, colors, typography, or interaction model when parity is required.

**Security:** MUST NOT optimize by removing authorization, trusting client permissions, bypassing policies/school context, or moving business rules to frontend.

---

# 35. DEPENDENCY RULE

Optimization does NOT justify new dependencies. Prefer existing stack. If not clearly favorable: DO NOT ADD THE DEPENDENCY.

---

# 36. PERFORMANCE BUDGETS

Evaluate bundle size, initial JS/CSS, network requests, payload size, render time, interaction latency, memory, LCP, INP, CLS when measurable.

Report regressions against established baselines (`PERFORMANCE-BUDGET.md`).

---

# 37. AUTOMATIC OPTIMIZATION LEVELS

| Level | Scope |
|-------|--------|
| **0 — Mandatory Safety** | Semantic HTML, responsive, RTL/LTR, a11y, no unnecessary requests/renders, no duplicate logic, no unnecessary dependencies |
| **1 — Standard** | Component decomposition, server pagination, DTOs, lazy loading, asset optimization, state locality, CSS reuse |
| **2 — Data** | Virtualization, cursor pagination, indexed search, server-side filter/sort |
| **3 — Advanced** | Aggressive memoization, advanced caching, workers — **only with evidence** |

Do NOT jump to Level 3 without evidence.

---

# 38. ANTI-OPTIMIZATION RULES

Do NOT: rewrite working architecture for performance; add libraries without justification; memoize everything; virtualize tiny lists; cache/preload/lazy-load everything; duplicate business logic; bypass server validation or authorization.

---

# 39. AUTOMATIC CHANGE CLASSIFICATION

Classify surface before modifying: PAGE, WINDOW, DIALOG, FORM, TABLE, DASHBOARD, WIDGET, COMPONENT, NAVIGATION, DATA QUERY, API, ASSET, CSS, RESPONSIVE, RUNTIME, PLATFORM — then apply only relevant rules.

---

# 40. OPTIMIZATION REPORTING

Proportional reporting only. Normal implementation:

```text
Optimization Check:
- Rendering: PASS
- DOM: PASS
- Network: PASS
- Responsive: PASS
- Accessibility: PASS
- RTL/LTR: PASS
- Memory: PASS
- Dependencies: NONE
```

Major surfaces: full Optimization Summary with baseline, risks, applied/deferred optimizations, validation, regression risk.

---

# 41. STOP CONDITIONS

STOP and request human approval if optimization would require: architectural change, new framework, new state-management system, major dependency, database schema change, API contract change, caching affecting authorization, changing business/security behavior, premature Window/Runtime/Platform Resolver, or moving business logic to UI.

---

# 42. AUTOMATIC OPTIMIZATION GATE

Before declaring UI implementation complete, verify:

```text
[ ] Architecture preserved
[ ] Business logic remains outside UI
[ ] No unnecessary dependencies
[ ] DOM reasonable
[ ] Rendering reasonable
[ ] State scope appropriate
[ ] Network requests appropriate
[ ] Payload appropriate
[ ] Tables optimized for data volume
[ ] Search optimized
[ ] Forms optimized
[ ] Dialog lifecycle optimized
[ ] Window lifecycle respected
[ ] Responsive verified
[ ] RTL/LTR verified
[ ] Accessibility preserved
[ ] CSS duplication avoided
[ ] Assets optimized
[ ] Memory cleanup verified
[ ] Security preserved
[ ] School/tenant isolation preserved
[ ] No PII leakage
[ ] Tests preserved
[ ] architecture:validate --fitness
[ ] security:validate
[ ] types:check / build
```

---

# 43. OPTIMIZATION DECISION RULE

Prefer: Simplest → Existing framework capability → Existing SIS component → Existing library → Small local optimization → New dependency → Architectural change.

Never jump directly to architectural complexity.

---

# 44–46. FRAMEWORK INDEPENDENCE & ADAPTIVE PRESENTATION

```text
SIS Core → Application → Contracts → UI Contracts → UI Runtime Adapter → React/Vue/Blazor → Web/PWA/Desktop/Mobile
```

Same business capability, different presentation (e.g. StudentDetailsSurface: Dialog / Panel / Full Page).

No future technology introduced solely for optimization.

---

# 47. NO PREMATURE OPTIMIZATION

Objective: Best Performance + Best UX + Lowest Complexity + Architecture Safety + Maintainability.

Optimal ≠ Maximum optimization.

---

# 48. DEFINITION OF DONE

Complete when: functional correctness + architecture + security + accessibility + responsive + RTL/LTR + **optimization compliance** + testing.

---

# 49–50. FINAL RULES

Whenever Cursor builds, creates, modifies, extends, fixes, redesigns, migrates, adds, updates, or implements any SIS UI surface — execute applicable optimization evaluation automatically.

```text
Build Correctly → Build Securely → Build Accessibly → Build Responsively → Build Efficiently → Measure → Optimize → Validate
```

NOT: Build First → Optimize Later.

Optimization is a permanent architectural quality requirement of SIS.
