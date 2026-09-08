# SIS Platform Contract

**Version:** 1.0  
**Date:** 2026-09-08  
**Constitution:** §8–14, §45–47, §76–77

---

## Principles

1. **Responsive** — layout scales (breakpoints, typography, spacing)
2. **Adaptive** — interaction model changes (navigation, dialogs, grids)
3. **Capability-based** — prefer viewport/pointer/touch over user-agent sniffing
4. **One feature, one business meaning** — presentation varies, rules do not

---

## Current Hooks (repository)

| Hook              | File                                     | Purpose                        |
| ----------------- | ---------------------------------------- | ------------------------------ |
| Mobile breakpoint | `resources/js/hooks/use-mobile.tsx`      | `(max-width: 767px)`           |
| Appearance / dark | `resources/js/hooks/use-appearance.tsx`  | Theme + `prefers-color-scheme` |
| Sidebar keyboard  | `resources/js/components/ui/sidebar.tsx` | Keyboard shortcuts             |

Extend these — do not fork per-page detection logic.

---

## Presentation Profiles

| Profile   | When                           | UI behavior                               |
| --------- | ------------------------------ | ----------------------------------------- |
| `desktop` | Wide viewport + mouse/keyboard | Dense layout; future multi-window         |
| `tablet`  | Medium viewport / touch        | Split view, tabs, collapsible nav         |
| `mobile`  | Narrow viewport / touch        | Pages, drawers, bottom sheets, card lists |

---

## Adaptive Transforms

| Desktop       | Mobile                     |
| ------------- | -------------------------- |
| Window / page | Full page                  |
| Dialog        | Full-screen / bottom sheet |
| Sidebar       | Drawer                     |
| Data table    | Cards / priority columns   |
| Toolbar       | Compact action bar         |
| Context menu  | Action sheet               |

---

## Forbidden

```text
if (iPhone) { businessLogicA }
if (Android) { businessLogicB }
if (React) { differentValidation }
```

Platform differences belong in **presentation adapters only**.

---

## Allowed Exception — Presentation-Only Device Labeling

**G-011 documented exception:** User-agent parsing is permitted **only** for default display labels in passkey registration UI.

| Allowed                                | File                                           | Purpose                                                        |
| -------------------------------------- | ---------------------------------------------- | -------------------------------------------------------------- |
| Default passkey device name suggestion | `resources/js/components/passkey-register.tsx` | Pre-fill "Chrome on Windows" style label — UX convenience only |

**Must NOT use UA/device detection for:**

- Authorization or permission decisions
- Business rules or workflow branching
- Security decisions
- Database behavior
- Tenant behavior
- Feature flags or eligibility

User may override the suggested label; backend must not trust client-provided device metadata for security.

---

## RTL / i18n

Arabic + English support required where project enables i18n.

RTL must cover: navigation, forms, tables, dialogs, icons, spacing — not random CSS flips.

See `react-inertia.mdc`.

---

## Validation Matrix (when UI changes)

| Surface         | Check                                    |
| --------------- | ---------------------------------------- |
| Windows desktop | Keyboard, focus, density                 |
| Tablet width    | Touch targets, split layout              |
| Mobile width    | Navigation stack, no horizontal overflow |
| RTL             | Mirror layout correctly                  |

Use browser devtools + `use-mobile` patterns; CI visual tests when available.

---

## PWA / Offline Readiness

Architecture must remain **compatible** with offline-aware UX (retry, reconnect) without bypassing auth or tenant isolation.

Offline support is not implemented by default.
