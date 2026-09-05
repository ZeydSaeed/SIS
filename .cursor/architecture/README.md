# SIS Architecture Reference

> **Version:** 2.1 — Enterprise SIS + 45K Province Scenario  
> **Maturity:** Architecture Ready ✅ | Development Ready ✅ | Production Proven ⏳  
> **Table count authority:** `database-blueprint.md` → **89 tables**

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
| [data-quality-rules.md](./data-quality-rules.md) | Integrity & validation rules |

### 45K Scenario
| File | Topic |
|------|-------|
| [capacity-planning-45k.md](./capacity-planning-45k.md) | Volume calculations |
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
| [improvement-matrix.md](./improvement-matrix.md) | Priority matrix (87/100 target) |
| [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md) | Mandatory DB checklist |
| [WORK-PLAN.md](./WORK-PLAN.md) | Phase A–F guide |
| [adr/](./adr/) | **8 Architecture Decision Records** |
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

## Self-Assessment Score: **87/100** — Strong Enterprise Foundation

See [improvement-matrix.md](./improvement-matrix.md) for layer scores and path to 95+.
