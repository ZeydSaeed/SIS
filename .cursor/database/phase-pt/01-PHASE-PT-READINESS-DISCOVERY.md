# PHASE PT — READINESS DISCOVERY

---

```text
Date: 2026-09-12
Mode: READ-ONLY discovery
```

## Live inventory

| Object | Status |
|--------|--------|
| Schema `promotion` | RESERVED in SchemaHelper / empty catalog |
| Schema `transfers` | RESERVED in SchemaHelper / empty catalog |
| `promotion.rules` | ABSENT (blueprint only) |
| `promotion.records` | ABSENT (blueprint only) |
| Application CQRS | ABSENT |
| Permissions | ABSENT |

## Upstream dependencies

| Dependency | Status | Impact on PT v1 |
|------------|--------|-----------------|
| Enrollment active | LIVE | Required FK target |
| GPA official | LIVE (results.year_gpa) but scale HDR history | Do NOT enforce min_gpa auto-gate |
| Graduation | LIVE Phase 3C | Consumer later — OUT of PT v1 mutate |
| Transfers policy | UNRESOLVED | HOLD HTTP |

## Risks if jumping to full lifecycle

```text
- Inventing GPA scale via promotion.rules.min_gpa enforcement
- Mutating enrollment / creating next-year enrollment without ballot
- Cross-school transfer RLS / dual-tenant writes
```
