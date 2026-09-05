# SIS Architecture Reference

> **Version:** 3.0 — Adaptive Expert Database Architecture  
> **Maturity:** Architecture Ready ✅ | Development Ready ✅ | Production Proven ⏳  
> **Table count authority:** `database-blueprint.md` → **89 tables**  
> **Core principle:** DO NOT OPTIMIZE FOR A NUMBER. OPTIMIZE FOR A MEASURED WORKLOAD.  
> **Intelligence:** Expert-Assisted + Controlled Self-Learning — PostgreSQL remains source of truth.

## Document Index

### Core Architecture
| File | Topic |
|------|-------|
| [01-principles-and-layers.md](./01-principles-and-layers.md) | Core principles, 4 layers |
| [02-infrastructure-phases.md](./02-infrastructure-phases.md) | Deployment phases |
| [normalization-and-cqrs.md](./normalization-and-cqrs.md) | **1NF–4NF + CQRS-lite** |
| [database-blueprint.md](./database-blueprint.md) | **89 tables — AUTHORITATIVE** |
| [database-dictionary.md](./database-dictionary.md) | Column meanings, PII, retention |
| [erd-overview.md](./erd-overview.md) | **ERD diagrams (Mermaid)** |
| [indexing-matrix.md](./indexing-matrix.md) | Per-table indexes |
| [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md) | Index justification rules |
| [DATABASE-GOVERNANCE.md](./DATABASE-GOVERNANCE.md) | Schema change governance |
| [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md) | **Adaptive optimization layer** |
| [schema-change-impact.md](./schema-change-impact.md) | Per-change impact analysis |
| [logical-data-architecture.md](./logical-data-architecture.md) | Logical vs physical model |
| [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md) | Measurable performance targets |
| [DATA-LIFECYCLE-MATRIX.md](./DATA-LIFECYCLE-MATRIX.md) | Retention & archival rules |
| [data-quality-rules.md](./data-quality-rules.md) | Integrity & validation rules |

### Intelligence Layer (v3.0)
| File | Topic |
|------|-------|
| [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md) | **Expert + Learning + Self-Healing design** |
| [DATABASE-KNOWLEDGE-BASE.md](./DATABASE-KNOWLEDGE-BASE.md) | Inference rules & heuristics |
| [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md) | Historical optimization learning |
| [SELF-HEALING-RUNBOOK.md](./SELF-HEALING-RUNBOOK.md) | Safe automated operational responses |

### 45K Baseline Scenario
| File | Topic |
|------|-------|
| [capacity-planning.md](./capacity-planning.md) | **Dynamic capacity model (authoritative)** |
| [capacity-planning-45k.md](./capacity-planning-45k.md) | Baseline snapshot (45K) |
| [batch-write-patterns.md](./batch-write-patterns.md) | Bulk INSERT / COPY |
| [peak-hour-strategy.md](./peak-hour-strategy.md) | 8:00–8:30 attendance peak |
| [cache-invalidation.md](./cache-invalidation.md) | Redis invalidation map |
| [seed-data-45k.md](./seed-data-45k.md) | Realistic test datasets |

### Application Layer
| File | Topic |
|------|-------|
| [laravel-architecture.md](./laravel-architecture.md) | Services, Actions, Jobs |
| [api-conventions.md](./api-conventions.md) | Routes, Inertia, pagination |
| [rules/react-inertia.mdc](../rules/react-inertia.mdc) | Frontend RTL/Arabic |

### Operations
| File | Topic |
|------|-------|
| [postgresql-tuning.md](./postgresql-tuning.md) | Production PG config |
| [zero-downtime-migrations.md](./zero-downtime-migrations.md) | Expand/Migrate/Contract |
| [dr-runbook.md](./dr-runbook.md) | **Disaster recovery steps** |
| [production-readiness.md](./production-readiness.md) | Pre-launch checklist |
| [testing-strategy.md](./testing-strategy.md) | Unit → load tests |
| [load-test-results.md](./load-test-results.md) | Results template |

### Governance & Decisions
| File | Topic |
|------|-------|
| [improvement-matrix.md](./improvement-matrix.md) | Priority matrix (91/100 target) |
| [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md) | Mandatory DB checklist |
| [WORK-PLAN.md](./WORK-PLAN.md) | Phase A–F guide |
| [adr/](./adr/) | **10 Architecture Decision Records** |
| [phases/](./phases/) | Phase implementation guides |

### Scalability & Security
| File | Topic |
|------|-------|
| [scalability-and-async.md](./scalability-and-async.md) | Redis, queues, MV |
| [security-audit-resilience.md](./security-audit-resilience.md) | RBAC, audit, backup |
| [rls-policies.md](./rls-policies.md) | RLS for 20 schools |

## Maturity Model

| Level | Status | Meaning |
|-------|--------|---------|
| Architecture Ready | ✅ | Design documented and reviewed |
| Development Ready | ✅ | Guides + rules + blueprint complete |
| Production Proven | ⏳ | Load test + DR + restore verified with numbers |

## Self-Assessment Score: **91/100** — Adaptive Expert Database Architecture

See [improvement-matrix.md](./improvement-matrix.md) for layer scores and path to 95+.
