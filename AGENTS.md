# AGENTS.md — SIS Project Guide for AI Agents

## Maturity

| Level | Status |
|-------|--------|
| Architecture Ready | ✅ |
| Development Ready | ✅ |
| Production Proven | ⏳ (needs load test + DR drill) |

**Score:** 93/100 (Design) · Operational Phases 2–6 ⏳

## Read First

| Priority | File | Purpose |
|----------|------|---------|
| 1 | `.cursor/architecture/README.md` | Full document index |
| 2 | `.cursor/architecture/WORK-PLAN.md` | Phase A–F guide |
| 3 | `.cursor/architecture/database-blueprint.md` | **89 tables — authoritative** |
| 4 | `.cursor/architecture/capacity-planning.md` | Dynamic capacity model |
| 5 | `.cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md` | **Adaptive optimization rules** |
| 6 | `.cursor/architecture/DATABASE-INTELLIGENCE-LAYER.md` | **Expert + Learning + Self-Healing** |
| 7 | `.cursor/architecture/normalization-and-cqrs.md` | 1NF–4NF + CQRS-lite |

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

## Cursor Rules

- `sis-core.mdc` — always apply
- `database-changes-mandatory.mdc` — database/**, app/Models/**
- `database-design.mdc` — database/**
- `laravel-patterns.mdc` — app/**
- `query-optimization.mdc` — app/**
- `react-inertia.mdc` — resources/js/**

## Key Docs (v3.1 Intelligence Stack)

- `DATABASE-INTELLIGENCE-SAFETY.md` — tiers, integrity gate, explainability
- `DATABASE-OPTIMIZATION-CONTEXT.md` — context fingerprint
- `DATABASE-SIMULATION-POLICY.md` — what-if before production
- `DATABASE-COST-MODEL.md` — total cost ranking
- `DATABASE-KNOWLEDGE-DRIFT.md` — drift + pattern deprecation
- `SIS-DOMAIN-KNOWLEDGE-BASE.md` — business criticality
- `DATABASE-WORKLOAD-CLASSIFICATION.md` — workload-aware SLOs

## Key New Docs (v3.0–v3.1)

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
- `laravel-architecture.md` — app layers
- `api-conventions.md` — HTTP/Inertia
- `adr/` — 11 architecture decisions (ADR-009–011 intelligence stack)

## When Implementing

1. Check WORK-PLAN phase guide
2. Check blueprint + dictionary
3. Follow database-change skill
4. Application: laravel-architecture + api-conventions
5. Frontend: react-inertia.mdc (RTL/Arabic from day one)
6. No microservices/Kafka/sharding — see ADR-008

## Single Source of Truth

**Table count = 89** from `database-blueprint.md` only.
