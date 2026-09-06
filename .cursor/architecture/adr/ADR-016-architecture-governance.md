# ADR-016: Architecture Governance & Fitness Tests

## Status
Accepted — 2026-09-06

## Context
Cursor Rules guide AI but do not compile or enforce. Teams need automated proof that new code respects Clean Architecture.

## Decision

1. **Dependency graph scanner** (`ArchitectureDependencyGraph`) validates import direction across layers.
2. **`php artisan architecture:graph`** displays allowed flow + fitness summary.
3. **`php artisan architecture:validate --fitness`** runs layer rules + dependency graph.
4. **`php artisan sis:make-feature {Context}`** scaffolds bounded context skeleton.
5. **Architecture fitness categories** in CI: domain_purity, dependency_direction, application_isolation, controller_thinness, handler_rules, intelligence_alignment.
6. **FEATURE-DONE.md** defines per-feature completion checklist.
7. **Source of truth order:** ARCHITECTURE-STACK → Validator → Tests → CI (Cursor = assistant only).

## Consequences
- New violations fail CI even if Cursor suggested wrong pattern.
- Legacy `app/Services/*` flagged unless `@architecture-legacy-allowed` while migrating.
