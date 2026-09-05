# ADR-001: PostgreSQL as Primary Database

**Status:** Accepted  
**Date:** 2026-09-05  
**Context:** SIS Enterprise — 45K students, 450M+ attendance rows over 10 years

## Decision

Use **PostgreSQL 16+** as the sole source of truth for all transactional and reporting data.

## Why

- Mature ACID transactions for academic records
- Native partitioning (LIST/RANGE) for attendance and grades
- Row-Level Security for 20-school multi-tenancy
- Materialized views for directorate dashboards
- JSONB for audit old/new values
- Strong FK, CHECK, and constraint support
- Read replicas for reporting separation

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| MySQL | Weaker partitioning story; team standardized on PostgreSQL |
| MongoDB | Poor fit for relational academic data + FK integrity |
| SQL Server | Licensing; PostgreSQL sufficient for scale |
| SQLite (production) | No concurrency/partitioning for 45K |

## Consequences

- Team needs PostgreSQL ops skills (VACUUM, PITR, partitioning)
- Laravel configured for `pgsql` driver
- Migrations must use PostgreSQL-specific features carefully
- **Review:** If single-table exceeds 1B rows, reassess sharding (unlikely in 10 years)

## Related

- [ADR-002-partitioning.md](./ADR-002-partitioning.md)
- [postgresql-tuning.md](../postgresql-tuning.md)
