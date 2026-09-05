# AGENTS.md — SIS Project Guide for AI Agents

## Maturity

| Level | Status |
|-------|--------|
| Architecture Ready | ✅ |
| Development Ready | ✅ |
| Production Proven | ⏳ (needs load test + DR drill) |

**Score:** 87/100 — Strong Enterprise Foundation

## Read First

| Priority | File | Purpose |
|----------|------|---------|
| 1 | `.cursor/architecture/README.md` | Full document index |
| 2 | `.cursor/architecture/WORK-PLAN.md` | Phase A–F guide |
| 3 | `.cursor/architecture/database-blueprint.md` | **89 tables — authoritative** |
| 4 | `.cursor/architecture/capacity-planning-45k.md` | 45K scale |
| 5 | `.cursor/architecture/normalization-and-cqrs.md` | 1NF–4NF + CQRS-lite |

## 45K Scenario

```
20 schools × 5 departments × 3 stages × 3 sections × 50 students = 45,000
10 years → ~450M attendance rows
```

## Database Changes — MANDATORY

```
1. .cursor/skills/database-change/SKILL.md
2. .cursor/architecture/DATABASE-CHANGE-CHECKLIST.md
3. .cursor/architecture/DATABASE-GOVERNANCE.md
4. Update database-blueprint.md (always)
```

## Cursor Rules

- `sis-core.mdc` — always apply
- `database-changes-mandatory.mdc` — database/**, app/Models/**
- `database-design.mdc` — database/**
- `laravel-patterns.mdc` — app/**
- `query-optimization.mdc` — app/**
- `react-inertia.mdc` — resources/js/**

## Key New Docs (v2.1)

- `erd-overview.md` — Mermaid ERD
- `database-dictionary.md` — column meanings
- `normalization-and-cqrs.md` — 1NF–4NF + CQRS
- `dr-runbook.md` — disaster recovery
- `seed-data-45k.md` — test datasets
- `testing-strategy.md` — test pyramid
- `cache-invalidation.md` — Redis map
- `laravel-architecture.md` — app layers
- `api-conventions.md` — HTTP/Inertia
- `adr/` — 8 architecture decisions

## When Implementing

1. Check WORK-PLAN phase guide
2. Check blueprint + dictionary
3. Follow database-change skill
4. Application: laravel-architecture + api-conventions
5. Frontend: react-inertia.mdc (RTL/Arabic from day one)
6. No microservices/Kafka/sharding — see ADR-008

## Single Source of Truth

**Table count = 89** from `database-blueprint.md` only.
