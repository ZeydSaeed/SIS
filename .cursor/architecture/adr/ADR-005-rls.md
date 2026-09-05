# ADR-005: Row-Level Security for 20-School Isolation

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Implement **PostgreSQL RLS** on enrollment, attendance, and grades **in addition to** application-level authorization.

```
Application Policies (primary)
        +
PostgreSQL RLS (defense-in-depth)
        +
Indexes on school_id
```

## Why

- 20 schools share one database — single mistake in query could leak data
- RLS enforces boundary even if developer forgets `where school_id = ?`
- Directorate and ministry roles need hierarchical access

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Application-only auth | Human error risk too high |
| Separate DB per school | 20× cost and ops |
| RLS only, no app auth | Poor UX; app policies needed for fine-grained actions |

## Consequences

- Middleware must set `app.current_school_id` session variable
- Must benchmark queries with RLS — no "milliseconds" claim without EXPLAIN
- Indexes on RLS filter columns mandatory
- **Review:** If RLS adds > 20% query overhead, optimize policies

## Related

- [rls-policies.md](../rls-policies.md)
- [security-audit-resilience.md](../security-audit-resilience.md)
