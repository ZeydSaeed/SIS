# ADR-017: Architecture Hardening (AST, Baseline, Contracts, Gates)

## Status
Accepted — 2026-09-06

## Context
Text-only `use` import checks can be bypassed via `app()`, `resolve()`, and FQCN strings. Teams need scalable governance as features grow.

## Decision

1. **ARCHITECTURE-BASELINE.json** — versioned rules for layers, complexity, security, intelligence.
2. **ArchitectureStaticAnalyzer** — token + regex scan for bypass patterns and FQCN references.
3. **FeatureContractValidator** — commands require handlers/results; sensitive commands require idempotency.
4. **ComplexityGateChecker** — handler LOC + cyclomatic complexity thresholds (ARCH-100+).
5. **SecurityFitnessChecker** — SchoolContext middleware + RLS migration presence.
6. **IntelligenceGovernanceChecker** — max auto tier, forbidden actions, learning cannot escalate permissions.
7. **`architecture:feature-check {Context}`** — per-context contract validation.

## Consequences
- CI fails on architectural bypass attempts even without `use` statements.
- Baseline JSON evolves without rewriting PHP for every new rule category.
