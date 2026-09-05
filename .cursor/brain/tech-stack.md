# Tech Stack

## Application Layer

| Component | Technology | Version |
|-----------|-----------|---------|
| Backend | Laravel | 13.x |
| Frontend | React + Inertia.js | React 19, Inertia 3 |
| Build | Vite + vite-plus | 8.x |
| UI | Radix UI + Tailwind CSS | v4 |
| Auth | Laravel Fortify + Passkeys + 2FA | — |
| Routing | Laravel Wayfinder | — |
| Language (BE) | PHP | 8.3+ |
| Language (FE) | TypeScript | 5.7+ |

## Data Layer (Target — Not Yet Implemented)

| Component | Technology | Phase |
|-----------|-----------|-------|
| Primary DB | PostgreSQL | Phase 1 |
| Cache | Redis | Phase 1 |
| Connection Pool | PgBouncer | Phase 2 (multi-node) |
| Read Replica | PostgreSQL Streaming Replication | Phase 2 |
| Reporting DB | Separate PostgreSQL / Materialized Views | Phase 3 |
| File Storage | Object Storage (S3-compatible) | Phase 1 |
| Job Queue | Laravel Queue (Redis driver) | Phase 1 |

## Infrastructure Phases

### Phase 1 — Start Simple

```
Application (Laravel)
    ↓
Redis (cache + queue)
    ↓
PostgreSQL (single instance)
```

### Phase 2 — Scale Reads & Connections

```
Application Nodes
    ↓
PgBouncer
    ↓
PostgreSQL Primary → Read Replica
```

### Phase 3 — Reporting Separation

```
Primary (OLTP writes)
    ↓ replication
Read Replica → Reporting / Materialized Views → Dashboards
```

## Development Tooling

| Tool | Purpose |
|------|---------|
| Laravel Pint | PHP formatting |
| PHPStan (Larastan) | Static analysis |
| PHPUnit | Backend tests |
| TypeScript | Frontend type checking |
| Laravel Pail | Log tailing |

## Coding Standards

- Migrations are versioned: `V001__create_organization.sql` pattern
- No manual DB changes in production
- No `SELECT *` in production API queries
- No N+1 queries — use eager loading
- Heavy operations via Laravel Queues
- Correlation ID on every request for tracing
- Idempotency keys for payments, imports, certificates

## PostgreSQL-Specific Notes

```sql
-- Primary keys (default)
id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY

-- Status fields
status SMALLINT  -- not TINYINT

-- Partial index example
CREATE INDEX ix_students_active
ON students.students (school_id, academic_level_id)
WHERE status = 'ACTIVE';

-- Covering index example
CREATE INDEX ix_students_school_status
ON students.students (school_id, status)
INCLUDE (student_code, full_name);
```
