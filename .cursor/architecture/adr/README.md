# ADR Index — Architecture Decision Records

> **Purpose:** Document *why* decisions were made — essential for 10–20 year maintainability.

| ADR | Topic | Status |
|-----|-------|--------|
| [ADR-001](./ADR-001-postgresql.md) | PostgreSQL as primary DB | Accepted |
| [ADR-002](./ADR-002-partitioning.md) | Partition by academic year | Accepted |
| [ADR-003](./ADR-003-bigint-identity.md) | BIGINT IDENTITY vs UUID | Accepted |
| [ADR-004](./ADR-004-redis.md) | Redis cache only | Accepted |
| [ADR-005](./ADR-005-rls.md) | Row-Level Security | Accepted (+ D6 amendment) |
| [ADR-006](./ADR-006-read-replica.md) | Read replica at launch | Accepted |
| [ADR-007](./ADR-007-reporting.md) | Materialized views | Accepted |
| [ADR-008](./ADR-008-cqrs-lite.md) | CQRS-lite, no microservices | Accepted |
| [ADR-009](./ADR-009-adaptive-governance.md) | Adaptive Database Governance | Accepted |
| [ADR-010](./ADR-010-intelligence-layer.md) | Database Intelligence Layer | Accepted |
| [ADR-011](./ADR-011-intelligence-v31.md) | Intelligence Layer v3.1 enhancements | Accepted |
| [ADR-012](./ADR-012-intelligence-v32.md) | Intelligence Layer v3.2 refinements | Accepted |
| [ADR-013](./ADR-013-outbox-pattern.md) | Transactional outbox | Accepted |
| [ADR-014](./ADR-014-idempotency-commands.md) | Idempotency keys | Accepted |
| [ADR-015](./ADR-015-rich-domain-dtos.md) | Rich domain DTOs | Accepted |
| [ADR-016](./ADR-016-architecture-governance.md) | Architecture governance | Accepted |
| [ADR-017](./ADR-017-architecture-hardening.md) | Architecture hardening | Accepted |
| [ADR-018](./ADR-018-autonomous-optimization-engine.md) | Autonomous optimization engine | Accepted |
| [ADR-019](./ADR-019-self-healing-performance-engine.md) | Self-healing performance engine | Accepted |
| [ADR-020](./ADR-020-phase-1-database-architecture.md) | Phase 1 DB architecture decision lock (D1–D6) | Accepted |

## When to Create New ADR

- Changing database engine or partitioning strategy
- Adding microservices or message bus
- Removing FK constraints
- Changing ID strategy
- Major denormalization
- Superseding Phase 1 locks (PK, schema rename policy, ERP big-bang, RLS model)

## Template

```markdown
# ADR-NNN: Title
**Status:** Proposed | Accepted | Superseded
**Date:** YYYY-MM-DD
## Decision
## Why
## Alternatives Considered
## Consequences
## Review condition
```
