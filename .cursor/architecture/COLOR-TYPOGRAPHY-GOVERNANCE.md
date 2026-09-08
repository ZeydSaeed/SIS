# SIS Mandatory Color & Typography Governance

**Version:** 1.0
**Status:** MANDATORY
**Date:** 2026-09-08
**Index rule:** `.cursor/rules/17-color-typography-governance.mdc`
**Constitution:** §6–7 (UI contracts, design system)

## Scope

Applies to all SIS UI surfaces: React/Inertia web (ACTIVE), future Blazor desktop, and all components listed in the user spec.

## Precedence

Constitution → Architecture → Security → Database → API → UI/UX → **this document** → `15-ui-optimization-governance` → framework rules.

## Implementation baseline (repository gap)

Current `resources/css/app.css` uses legacy shadcn/Instrument Sans/oklch tokens — **non-compliant** with this SSOT until a dedicated design-token migration. **All new and modified UI** must comply. Do not extend legacy colors/fonts on touched surfaces.

## Design System Enforcement v1.0

### Status

**MANDATORY**

This document defines the ONLY approved visual color palette and typography system for the SIS application.

These rules apply to:

* Every page
* Every window
* Every form
* Every dialog
* Every modal
* Every drawer
* Every panel
* Every toolbar
* Every command bar
* Every navigation element
* Every table/grid
* Every form control
* Every button
* Every badge
* Every notification
* Every card
* Every empty state
* Every loading state
* Every error state
* Every success state
* Every React component
* Every Inertia page
* Every future UI surface

No UI implementation may bypass this governance.

---

# 1. Absolute Color Restriction

The SIS application has exactly FIVE approved base colors.

```css
--twilight-indigo: #3a405a;
--powder-blue: #aec5eb;
--powder-petal: #f9dec9;
--powder-blush: #e9afa3;
--ash-brown: #685044;
```

These five colors are the **SSOT base palette**.

No other arbitrary colors may be introduced.

---

# 2. Approved Color Palette

## 2.1 Twilight Indigo

```text
Name: Twilight Indigo
HEX: #3A405A
RGB: 58, 64, 90
```

Semantic role:

**Primary dark / navigation / command surfaces / strong emphasis**

Preferred uses:

* Application shell
* Top application bar
* Main navigation
* Primary navigation background
* Active navigation background
* Command bar
* Ribbon background
* Strong headings
* Primary dark buttons
* Primary icon containers
* Selected high-emphasis controls
* Footer where appropriate
* Strong separators

Do NOT use it indiscriminately as the background of the entire application.

---

# 3. Powder Blue

```text
Name: Powder Blue
HEX: #AEC5EB
RGB: 174, 197, 235
```

Semantic role:

**Interactive / informational / selection / secondary emphasis**

Preferred uses:

* Hover backgrounds
* Selected-row backgrounds
* Selected navigation states
* Focus-support surfaces
* Informational panels
* Secondary buttons
* Active tabs
* Soft emphasis
* Interactive highlights
* Light command surfaces

Do not use Powder Blue as the default text color.

---

# 4. Powder Petal

```text
Name: Powder Petal
HEX: #F9DEC9
RGB: 249, 222, 201
```

Semantic role:

**Warm soft surface / form grouping / contextual emphasis**

Preferred uses:

* Soft panels
* Form sections
* Secondary cards
* Contextual areas
* Warm highlighted surfaces
* Empty states
* Supporting containers
* Non-critical attention areas

Powder Petal must generally use a dark text color from the approved palette when contrast permits.

---

# 5. Powder Blush

```text
Name: Powder Blush
HEX: #E9AFA3
RGB: 233, 175, 163
```

Semantic role:

**Attention / warning / destructive emphasis**

Preferred uses:

* Warning surfaces
* Attention states
* Validation emphasis
* Destructive-action emphasis
* Important notifications
* Unsaved-change indicators
* Attention badges
* Soft error/warning surfaces

Do not use Powder Blush as the default page background.

Do not use it for every error or every notification.

Its semantic meaning must remain meaningful.

---

# 6. Ash Brown

```text
Name: Ash Brown
HEX: #685044
RGB: 104, 80, 68
```

Semantic role:

**Secondary dark / text / borders / muted emphasis**

Preferred uses:

* Secondary text
* Supporting headings
* Labels
* Borders
* Dividers
* Icons
* Metadata
* Secondary buttons
* Form labels
* Supporting UI text

Ash Brown is the primary approved dark text alternative where contrast requirements are satisfied.

---

# 7. White, Black and Gray Restrictions

Do NOT introduce arbitrary:

```text
#FFFFFF
#000000
#808080
#F5F5F5
#EEEEEE
#CCCCCC
```

or similar colors simply because they are conventional UI colors.

The design system must remain based on the five approved colors.

If a component requires a lighter/darker variation, it MUST be generated systematically from one of the approved base colors.

Do not manually invent unrelated HEX values.

---

# 8. Color Derivatives

Derived colors are allowed ONLY when they are mathematically derived from an approved base color.

Examples:

```text
Tint
Shade
Alpha
Opacity
Lightness adjustment
Darkness adjustment
```

However:

### IMPORTANT

A derivative does NOT become a new semantic color.

Every derivative must retain a parent token.

Example:

```css
--color-primary: var(--twilight-indigo);
--color-primary-hover: derived(--twilight-indigo);
--color-primary-active: derived(--twilight-indigo);
```

Not:

```css
--random-blue: #324f8a;
```

The latter is prohibited.

---

# 9. Design Token Architecture

Do NOT scatter HEX values throughout JSX/CSS.

Create centralized semantic tokens.

Recommended structure:

```css
:root {
    /* Base palette */

    --twilight-indigo: #3a405a;
    --powder-blue: #aec5eb;
    --powder-petal: #f9dec9;
    --powder-blush: #e9afa3;
    --ash-brown: #685044;

    /* Semantic roles */

    --color-primary: var(--twilight-indigo);
    --color-primary-text: var(--powder-petal);

    --color-secondary: var(--powder-blue);

    --color-surface: var(--powder-petal);
    --color-surface-soft: var(--powder-blue);

    --color-text-primary: var(--twilight-indigo);
    --color-text-secondary: var(--ash-brown);

    --color-border: var(--ash-brown);

    --color-attention: var(--powder-blush);

    /* UI states */

    --color-hover: ...;
    --color-active: ...;
    --color-selected: ...;
    --color-focus: ...;
    --color-disabled: ...;
    --color-danger: ...;
    --color-warning: ...;
    --color-info: ...;
}
```

The exact derivative values must be calculated and documented rather than invented.

---

# 10. Semantic Color Mapping

Use the following hierarchy.

| UI Role              | Preferred Color                   |
| -------------------- | --------------------------------- |
| Application shell    | Twilight Indigo                   |
| Primary navigation   | Twilight Indigo                   |
| Primary command      | Twilight Indigo                   |
| Main heading         | Twilight Indigo                   |
| Primary text         | Twilight Indigo                   |
| Secondary text       | Ash Brown                         |
| Border               | Ash Brown                         |
| Hover                | Powder Blue derivative            |
| Selected             | Powder Blue                       |
| Informational        | Powder Blue                       |
| Soft surface         | Powder Petal                      |
| Attention            | Powder Blush                      |
| Warning              | Powder Blush                      |
| Destructive emphasis | Powder Blush                      |
| Secondary surface    | Powder Petal                      |
| Secondary action     | Ash Brown / Powder Blue           |
| Focus indicator      | Powder Blue + sufficient contrast |
| Disabled             | Approved low-emphasis derivative  |

---

# 11. Background/Text Pairing Rules

Never choose text color independently from the background.

The background determines the allowed text color.

For every component:

```text
Background
    ↓
Contrast evaluation
    ↓
Approved text color
    ↓
Font weight
    ↓
Font size
```

Do not simply use:

```css
color: var(--ash-brown);
```

everywhere.

The correct text color must be selected according to the actual background and contrast.

---

# 12. Contrast Requirement

Every text/background combination MUST be checked for accessibility.

Minimum targets:

```text
Normal text: >= 4.5:1
Large text:  >= 3:1
```

Interactive states must also be checked:

* default
* hover
* active
* selected
* focus
* disabled
* validation
* warning
* error
* success
* loading

Do not approve a color pairing merely because it visually looks attractive.

---

# 13. Typography — Absolute Restriction

Only these four font families are permitted:

```text
Segoe UI
Tahoma
Calibri
Aptos
```

No other font family may be introduced.

Prohibited examples:

```text
Arial
Roboto
Inter
Open Sans
Poppins
Montserrat
Lato
Nunito
Ubuntu
Noto Sans
system-ui
sans-serif
serif
```

Do not install an external font package.

Do not import Google Fonts.

Do not use remote font providers.

---

# 14. Font Hierarchy

Default:

```css
font-family: "Segoe UI", Tahoma, Calibri, Aptos;
```

### Primary UI font

```text
Segoe UI
```

Use Segoe UI for:

* Application shell
* Navigation
* Commands
* Buttons
* Labels
* Forms
* Tables
* Dialogs
* Headings
* General application content

This creates the strongest Windows/Office-style visual identity.

---

# 15. Tahoma

Tahoma is an approved fallback and may be used for:

* Compact data-heavy interfaces
* Dense tables
* Small metadata
* Legacy-compatible screens
* High-density administrative views

Do not switch randomly between Segoe UI and Tahoma.

The choice must be intentional and documented.

---

# 16. Calibri

Calibri may be used for:

* Document-like content
* Long-form administrative content
* Report-oriented views
* Print-oriented UI
* Content visually inspired by Office documents

It should not replace Segoe UI globally.

---

# 17. Aptos

Aptos may be used for:

* Modern Office-inspired document surfaces
* Report/document areas
* Office-like content experiences

It must not be introduced as a global application font without architectural justification.

---

# 18. Recommended Font Policy

Default:

```css
font-family: "Segoe UI", Tahoma, Calibri, Aptos;
```

Application UI:

```text
Segoe UI
```

Dense administrative/data views:

```text
Segoe UI
```

Legacy-compatible compact views:

```text
Tahoma
```

Document/report surfaces:

```text
Calibri
```

Modern Office-inspired document surfaces:

```text
Aptos
```

Do not mix all four fonts within the same component.

---

# 19. Font Weight System

Use a controlled hierarchy.

Recommended:

```text
400 = Regular
500 = Medium where available
600 = Semibold
700 = Bold
```

Use:

```text
400
```

for normal body text.

Use:

```text
600
```

for:

* headings
* important labels
* selected navigation
* primary commands

Use:

```text
700
```

sparingly for:

* major titles
* critical emphasis
* exceptional alerts

Avoid excessive bold text.

---

# 20. Font Size Hierarchy

Use a controlled type scale.

Recommended:

```text
12px  — compact metadata / secondary information
13px  — dense UI
14px  — standard UI
16px  — primary body / important controls
18px  — section heading
20px  — major section heading
24px  — page heading
28px+ — exceptional display heading
```

Do not create arbitrary font sizes without justification.

---

# 21. Arabic / RTL Typography

The SIS supports Arabic RTL.

When the interface is Arabic:

```text
direction: rtl;
text-align: right;
```

unless the component has a specific reason to use another alignment.

Use Segoe UI as the primary font because it supports Arabic and is designed for UI readability.

Do not introduce a separate Arabic font outside the four approved fonts.

---

# 22. Application Shell

The application shell should use:

```text
Background:
Twilight Indigo

Primary shell text:
Powder Petal

Secondary shell text:
Powder Blue

Active/selected navigation:
Powder Blue-derived surface

Attention:
Powder Blush
```

The shell must remain visually restrained.

Do not use all five colors simultaneously at equal visual weight.

---

# 23. Main Workspace

The main application workspace should prioritize readability.

Recommended hierarchy:

```text
Workspace background
    ↓
Powder Petal / controlled light surface

Primary text
    ↓
Twilight Indigo

Secondary text
    ↓
Ash Brown

Borders
    ↓
Ash Brown-derived subtle border

Interactive state
    ↓
Powder Blue
```

The user should immediately distinguish:

1. application chrome
2. workspace
3. content
4. controls
5. state

---

# 24. Forms

Forms must use:

```text
Page/Form surface:
Powder Petal or approved light derivative

Labels:
Ash Brown / Twilight Indigo according to contrast

Input text:
Twilight Indigo

Input border:
Ash Brown-derived

Focus:
Powder Blue

Primary action:
Twilight Indigo

Attention:
Powder Blush
```

Do not make every input colorful.

Professional enterprise UI should remain calm and structured.

---

# 25. Tables and Data Grids

Data grids must prioritize readability.

Recommended:

```text
Grid background:
light approved surface

Header:
Twilight Indigo

Header text:
Powder Petal

Primary data:
Twilight Indigo

Secondary metadata:
Ash Brown

Selected row:
Powder Blue

Attention:
Powder Blush

Borders:
Ash Brown-derived subtle border
```

Avoid excessive row coloring.

The selected state must be clearly distinguishable.

---

# 26. Buttons

### Primary

```text
Background: Twilight Indigo
Text: approved high-contrast light color
```

### Secondary

```text
Background: Powder Blue
Text: Twilight Indigo / Ash Brown according to contrast
```

### Neutral

Use approved surface colors with Ash Brown/Twilight Indigo.

### Attention / destructive

Use Powder Blush with a contrast-safe dark text treatment.

Never introduce conventional red/green/blue buttons outside the approved palette.

---

# 27. Navigation

Navigation hierarchy:

```text
Shell
    Twilight Indigo

Normal item
    Twilight Indigo-based surface

Hover
    Powder Blue-derived surface

Selected
    Powder Blue

Selected text
    Contrast-safe approved dark color

Attention
    Powder Blush
```

Do not use color alone to communicate navigation state.

Use:

* icon
* typography
* border
* indicator
* position
* aria/state attributes

where appropriate.

---

# 28. Cards

Cards should not become colorful containers everywhere.

Recommended:

```text
Card:
Powder Petal / light approved derivative

Title:
Twilight Indigo

Supporting text:
Ash Brown

Interactive hover:
Powder Blue-derived

Attention:
Powder Blush
```

Use spacing and hierarchy before using color.

---

# 29. Dialogs and Modals

Dialog hierarchy:

```text
Dialog surface
    approved light surface

Title
    Twilight Indigo

Body
    Ash Brown / Twilight Indigo

Primary action
    Twilight Indigo

Secondary action
    Powder Blue

Attention
    Powder Blush
```

Do not create arbitrary gray modal overlays.

If transparency is required, use alpha/opacity derived from approved colors.

---

# 30. Focus State

Every keyboard-focusable element must have a visible focus state.

The focus indicator must:

* be clearly visible
* use an approved color
* maintain sufficient contrast
* not depend only on subtle shadows

Preferred focus family:

```text
Powder Blue
```

with a contrast-safe supporting treatment.

---

# 31. Hover / Active / Selected

Do not invent a new color for every state.

Use controlled derivatives:

```text
Default
↓
Hover
↓
Active
↓
Selected
↓
Disabled
```

All must originate from the component's semantic base color.

---

# 32. Error / Warning / Attention

Do not introduce:

```text
red
orange
yellow
green
```

from outside the approved palette.

Use:

```text
Powder Blush
```

for attention/warning/destructive emphasis.

However, color must not be the only way the user understands the state.

Also use:

* icon
* text
* semantic HTML
* ARIA
* border
* status label

where appropriate.

---

# 33. Success State

Because the palette does not contain a conventional green, do NOT introduce green.

Success should be communicated using:

* iconography
* text
* confirmation messaging
* approved Twilight Indigo / Powder Blue styling
* structural indicators

The color palette must remain intact.

---

# 34. Icons

Icons must follow the same palette.

Default:

```text
Ash Brown
```

Primary:

```text
Twilight Indigo
```

Interactive:

```text
Powder Blue-derived
```

Attention:

```text
Powder Blush
```

Do not introduce arbitrary icon colors.

---

# 35. Shadows

Shadows must NOT introduce arbitrary colored shadows.

If shadows are necessary:

* use neutral opacity based on approved dark palette
* keep them subtle
* avoid decorative excessive shadows

Prefer hierarchy through:

1. spacing
2. borders
3. surfaces
4. elevation
5. subtle shadow

---

# 36. Borders and Dividers

Default:

```text
Ash Brown-derived subtle border
```

Important separators:

```text
Ash Brown
```

Active/selected separators:

```text
Powder Blue
```

Attention:

```text
Powder Blush
```

Do not use arbitrary gray borders.

---

# 37. Dark / Light Themes

If themes are implemented, both themes MUST remain within the five-color system.

Do not create an independent dark palette containing unrelated colors.

Each theme must derive from:

```text
Twilight Indigo
Powder Blue
Powder Petal
Powder Blush
Ash Brown
```

Theme transformations must preserve semantic roles.

---

# 38. Design Token Rule

All UI code must consume semantic tokens.

Preferred:

```css
background: var(--color-primary);
color: var(--color-text-on-primary);
```

Avoid:

```css
background: #3a405a;
```

inside individual components.

The HEX values belong to the central Design System.

---

# 39. Forbidden UI Code

The following is prohibited:

```css
color: red;
color: blue;
color: green;
color: orange;
color: gray;
background: white;
background: black;
```

Also prohibited:

```css
font-family: Arial;
font-family: Roboto;
font-family: Inter;
font-family: "Open Sans";
font-family: system-ui;
```

And prohibited:

```html
style="color: ..."
style="background: ..."
```

when a semantic token already exists.

---

# 40. Component-Level Enforcement

Before creating or modifying a component, Cursor MUST answer:

```text
1. Which semantic color tokens does this component use?
2. Which approved base colors do those tokens derive from?
3. Which font family is used?
4. Why was that font selected?
5. What is the text/background contrast?
6. What are hover/active/selected/focus states?
7. What is the RTL behavior?
8. Does an existing component already solve this problem?
```

---

# 41. Existing Components

Before creating a new visual component:

Search the existing project for an equivalent component.

Reuse before creating.

Do not create:

```text
NewButton
CustomButton
SpecialButton
AnotherButton
```

if the existing design system already provides the required behavior.

---

# 42. Automatic Governance Check

For every UI modification, inspect for:

### Colors

```text
Forbidden HEX
Forbidden rgb()
Forbidden rgba()
Forbidden named colors
Forbidden CSS variables
Forbidden gradients using unauthorized colors
```

### Fonts

```text
Unauthorized font-family
External font imports
Google Fonts
Remote fonts
```

### Inline styles

Check for unauthorized visual values.

---

# 43. Gradient Restriction

Gradients are NOT the default design language.

If a gradient is necessary:

* it must use only approved colors or their controlled derivatives
* it must have a documented purpose
* it must not reduce readability
* it must not become decorative noise

Do not introduce random gradients.

---

# 44. Visual Hierarchy

The five colors must NOT have equal visual weight.

Use hierarchy:

```text
Twilight Indigo
    ↓
Primary structural color

Powder Petal
    ↓
Main light surface

Ash Brown
    ↓
Secondary text / structural support

Powder Blue
    ↓
Interaction / selection

Powder Blush
    ↓
Attention / exceptional state
```

This creates a professional enterprise visual system rather than a colorful dashboard.

---

# 45. Professional UI Principle

The design must resemble a professional enterprise desktop/web application.

Prioritize:

```text
Consistency
Readability
Hierarchy
Predictability
Density
Keyboard usability
Accessibility
Information clarity
Visual restraint
```

Do not prioritize:

```text
Decorative effects
Excessive colors
Excessive gradients
Large rounded cards everywhere
Visual noise
Animation for decoration
```

---

# 46. Accessibility

Every UI component must be evaluated for:

* text contrast
* focus visibility
* keyboard navigation
* readable font sizes
* RTL support
* semantic structure
* screen-reader semantics
* non-color state communication
* disabled-state clarity

Color must never be the only mechanism for conveying meaning.

---

# 47. Mandatory Visual QA

Before declaring a UI task complete:

Inspect the resulting UI and verify:

```text
[ ] Only approved colors are used
[ ] Only approved fonts are used
[ ] Semantic tokens are used
[ ] No unauthorized HEX values exist
[ ] No unauthorized font imports exist
[ ] Text contrast is acceptable
[ ] Hover state is correct
[ ] Active state is correct
[ ] Selected state is correct
[ ] Focus state is visible
[ ] Disabled state is clear
[ ] Error/attention state is clear
[ ] RTL is correct
[ ] Typography hierarchy is consistent
[ ] Existing components were reused
[ ] No unnecessary visual decoration exists
```

---

# 48. Governance Priority

This design system is mandatory for all SIS UI work.

When a new UI requirement conflicts with this system:

Do NOT silently introduce a new color or font.

Instead:

```text
1. Identify the conflict.
2. Explain why the existing palette cannot satisfy it.
3. Propose a derived token or semantic mapping.
4. Request architecture/design approval if necessary.
5. Never bypass the design system silently.
```

---

# 49. Final Rule

## The SIS visual identity is immutable by default.

### Allowed colors:

```text
#3A405A
#AEC5EB
#F9DEC9
#E9AFA3
#685044
```

### Allowed fonts:

```text
Segoe UI
Tahoma
Calibri
Aptos
```

Anything outside these lists requires explicit human approval and a documented governance change.

Do not invent visual values.

Do not import external fonts.

Do not introduce arbitrary colors.

Do not bypass semantic design tokens.

Do not make individual pages visually independent from the SIS Design System.

Every new UI must look like it belongs to the same SIS product.

---

## Related contracts

| Contract | Relationship |
|----------|--------------|
| `UI-CONTRACT.md` | Behavioral UI contract; defers color/font SSOT to this document |
| `02-ui-ux.mdc` | Framework-independent UX; references this palette |
| `DESKTOP-UI-GOVERNANCE.md` | Desktop shell uses same design system |
| `15-ui-optimization-governance.mdc` | Optimization must not bypass contrast/a11y |
