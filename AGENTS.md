# AGENTS.md — SIS Project Guide for AI Agents

## Maturity

| Level              | Status                          |
| ------------------ | ------------------------------- |
| Architecture Ready | ✅                              |
| Development Ready  | ✅                              |
| Production Proven  | ⏳ (needs load test + DR drill) |

**Score:** Design 93–94/100 · Operational ~60/100

## Read First

| Priority | File                                                                   | Purpose                                            |
| -------- | ---------------------------------------------------------------------- | -------------------------------------------------- |
| 0        | `.cursor/architecture/SIS-CONSTITUTION.md`                             | **Permanent Engineering Constitution v2.0**        |
| 0b       | `.cursor/rules/00-SIS-CONSTITUTION.mdc`                                | Constitution index (always applied)                |
| 0b2      | `.cursor/rules/01-ARCHITECTURE.mdc`                                    | Mandatory architecture principles (always applied) |
| 0c       | `.cursor/architecture/GOVERNANCE-MAP.md`                               | Constitution → rules/docs map                      |
| 1        | `.cursor/architecture/README.md`                                       | Full document index                                |
| 2        | `.cursor/architecture/WORK-PLAN.md`                                    | Phase A–F guide                                    |
| 3        | `.cursor/architecture/database-blueprint.md`                           | **87 blueprint objects — authoritative** (+ intelligence separate) |
| 4        | `.cursor/architecture/capacity-planning.md`                            | Dynamic capacity model                             |
| 5        | `.cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md`                 | **Adaptive optimization rules**                    |
| 6        | `.cursor/architecture/DATABASE-INTELLIGENCE-LAYER.md`                  | **Expert + Learning + Self-Healing**               |
| 7        | `.cursor/architecture/normalization-and-cqrs.md`                       | 1NF–4NF + CQRS-lite                                |
| 8        | `.cursor/architecture/ARCHITECTURE-STACK.md`                           | **Clean + DDD + CQRS — enforced**                  |
| 9        | `.cursor/architecture/optimization/SELF-HEALING-PERFORMANCE-PROMPT.md` | **Self-healing adaptive engine (24/7)**            |
| 10       | `.cursor/architecture/optimization/AUTONOMOUS-OPTIMIZATION-PROMPT.md`  | Observe → Recommend → Autonomous                   |

## Application Code — MANDATORY

```
1. .cursor/skills/application-feature/SKILL.md
2. .cursor/architecture/ARCHITECTURE-STACK.md
3. .cursor/rules/clean-architecture.mdc
4. php artisan sis:make-feature / sis:make-command / sis:make-query (scaffold)
5. php artisan architecture:validate --fitness (must pass)
6. php artisan architecture:feature-check {Context} (feature contract)
7. php artisan architecture:graph (dependency audit)
```

## Performance Optimization — MANDATORY

```
1. .cursor/skills/autonomous-optimization/SKILL.md
2. .cursor/architecture/optimization/SELF-HEALING-PERFORMANCE-PROMPT.md
3. OPTIMIZATION_MODE=observe (default — monitor only, no blind auto-modify)
4. Background: RunSelfHealingCycleJob every 5 min (no manual observe required)
5. php artisan optimization:status / optimization:health
6. ONE PROBLEM → ONE OPTIMIZATION → ONE VALIDATION
```

## 45K Baseline (Not Architectural Ceiling)

```
20 schools × 5 departments × 3 stages × 3 sections × 50 students = 45,000
10 years → ~450M attendance rows (at baseline variables)
```

Re-evaluate when student count, table size, or query patterns change significantly.

## Database Changes — MANDATORY

```
1. .cursor/skills/database-change/SKILL.md
2. .cursor/architecture/DATABASE-CHANGE-CHECKLIST.md
3. .cursor/architecture/schema-change-impact.md
4. .cursor/architecture/DATABASE-GOVERNANCE.md
5. .cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md
6. Update database-blueprint.md (always)
```

## Adaptive Governance Principle

```text
DO NOT OPTIMIZE FOR A NUMBER.
OPTIMIZE FOR A MEASURED WORKLOAD.
```

Every optimization (index, partition, cache, MV) requires before/after evidence.
See PERFORMANCE-BUDGET.md for measurable targets.

## Intelligence Layer (v3.0)

PostgreSQL = source of truth. AI/Expert Engine = analysis + recommendation only.

```text
Rule Engine + Knowledge Base + Learning Engine → Recommendation → Human Gate → Execute
```

Tier 1 only: safe auto-actions (pool resize, route replica traffic) — see SELF-HEALING-RUNBOOK.md.
Never auto-execute schema changes (DROP INDEX, ALTER TABLE, disable RLS).

## Design System & Color/Typography

**SSOT:** `.cursor/architecture/COLOR-TYPOGRAPHY-GOVERNANCE.md` + `17-color-typography-governance.mdc`

| Rule | Scope |
|------|--------|
| Five approved base colors | Twilight Indigo, Powder Blue, Powder Petal, Powder Blush, Ash Brown |
| Four approved fonts | Segoe UI, Tahoma, Calibri, Aptos — no Google Fonts |
| Legacy baseline | `app.css` shadcn/Instrument Sans — migrate on touch; do not extend on new UI |

## Cursor Rules

**Always applied:** `00-SIS-CONSTITUTION.mdc`, `01-ARCHITECTURE.mdc`, `sis-core.mdc`, `11-change-control.mdc`, `15-ui-optimization-governance.mdc`, `17-color-typography-governance.mdc`

**Path-scoped (see GOVERNANCE-MAP.md for full inventory):**

- `10-MODULES.mdc` — module boundaries
- `12-DOCUMENTATION.mdc` — documentation when contracts change
- `database-changes-mandatory.mdc` — database/**, app/Models/**
- `database-design.mdc` — database/**
- `architecture-governance.mdc` — app/** (feature creation workflow)
- `clean-architecture.mdc` — app/** (Clean + DDD + CQRS)
- `laravel-patterns.mdc` — app/** (Infrastructure Laravel glue — subordinate)
- `query-optimization.mdc` — app/**
- `react-inertia.mdc` — resources/js/** (implementation; UX principles in `02-ui-ux.mdc`)
- `16-desktop-ui-governance.mdc` — `clients/sis-desktop/**` (Blazor Hybrid — **FUTURE / ADR required**)

## Desktop UI (Future)

**Status:** FUTURE / ADR REQUIRED — not the current web stack.

| Runtime | Status | Governance |
|---------|--------|------------|
| `web-inertia` | **ACTIVE** | `UI-CONTRACT.md`, `react-inertia.mdc` |
| `desktop-blazor-hybrid` | **FUTURE** | `DESKTOP-UI-GOVERNANCE.md`, `16-desktop-ui-governance.mdc` |

Do not introduce Blazor, .NET, or desktop host dependencies without ADR + human approval.

## Key Docs (v3.2)

- `INTELLIGENCE-GLOSSARY.md` — Prometheus, P95, ADR, confidence, etc.
- `DATABASE-DEPENDENCY-GRAPH.md` — blast radius before schema changes
- `DATABASE-EVIDENCE-THRESHOLDS.md` — static → measured thresholds
- `DATABASE-OPTIMIZATION-LEARNING.md` — calibration, recency, human feedback

**Next implementation step:** Phase 2 Observability — not more documentation.

- `DATABASE-INTELLIGENCE-LAYER.md` — expert + learning + self-healing architecture
- `DATABASE-KNOWLEDGE-BASE.md` — inference rules & heuristics
- `DATABASE-OPTIMIZATION-LEARNING.md` — historical optimization learning
- `SELF-HEALING-RUNBOOK.md` — safe automated operational responses
- `DATABASE-ADAPTIVE-GOVERNANCE.md` — optimization by measurement
- `PERFORMANCE-BUDGET.md` — measurable latency targets
- `DATA-LIFECYCLE-MATRIX.md` — retention & archival
- `schema-change-impact.md` — per-change impact analysis
- `logical-data-architecture.md` — logical vs PostgreSQL physical
- `capacity-planning.md` — dynamic capacity model
- `erd-overview.md` — Mermaid ERD
- `database-dictionary.md` — column meanings
- `normalization-and-cqrs.md` — 1NF–4NF + CQRS
- `dr-runbook.md` — disaster recovery
- `seed-data-45k.md` — test datasets
- `testing-strategy.md` — test pyramid
- `cache-invalidation.md` — Redis map
- `ARCHITECTURE-STACK.md` — **authoritative** app layers (handlers, Domain, Infrastructure)
- `laravel-architecture.md` — **LEGACY / non-authoritative** historical reference only
- `api-conventions.md` — HTTP/Inertia
- `adr/` — 12 architecture decisions (ADR-009–012 intelligence stack)

## When Implementing

1. Check WORK-PLAN phase guide
2. Check blueprint + dictionary
3. Follow database-change skill
4. Application: ARCHITECTURE-STACK.md + api-conventions (not legacy laravel-architecture.md)
5. Frontend: react-inertia.mdc (RTL/Arabic from day one)
6. No microservices/Kafka/sharding — see ADR-008

## Single Source of Truth

**Blueprint object count = 87** from `database-blueprint.md` only (intelligence tables are separate).
