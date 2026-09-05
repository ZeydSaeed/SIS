# ADR Index — Architecture Decision Records

> **Purpose:** Document *why* decisions were made — essential for 10–20 year maintainability.

| ADR | Topic | Status |
|-----|-------|--------|
| [ADR-001](./ADR-001-postgresql.md) | PostgreSQL as primary DB | Accepted |
| [ADR-002](./ADR-002-partitioning.md) | Partition by academic year | Accepted |
| [ADR-003](./ADR-003-bigint-identity.md) | BIGINT IDENTITY vs UUID | Accepted |
| [ADR-004](./ADR-004-redis.md) | Redis cache only | Accepted |
| [ADR-005](./ADR-005-rls.md) | Row-Level Security | Accepted |
| [ADR-006](./ADR-006-read-replica.md) | Read replica at launch | Accepted |
| [ADR-007](./ADR-007-reporting.md) | Materialized views | Accepted |
| [ADR-008](./ADR-008-cqrs-lite.md) | CQRS-lite, no microservices | Accepted |

## When to Create New ADR

- Changing database engine or partitioning strategy
- Adding microservices or message bus
- Removing FK constraints
- Changing ID strategy
- Major denormalization

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
