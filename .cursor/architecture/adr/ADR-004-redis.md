# ADR-004: Redis as Cache Only — Not Source of Truth

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Redis used for **cache, sessions, and queue** — never as authoritative data store.

```
PostgreSQL = Source of Truth
Redis      = Performance layer
```

## Cached Data

Reference data, permissions, dashboard aggregates (short TTL).  
See [cache-invalidation.md](../cache-invalidation.md).

## Never Cached as Primary

Individual grades, attendance records, financial transactions.

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Redis primary for sessions only + PG | Accepted for sessions |
| Write-through cache for grades | Consistency risk |
| Redis JSON as student store | Violates academic integrity requirements |

## Consequences

- Cache invalidation map required and maintained
- App must handle Redis failure gracefully (fallback to PostgreSQL)
- **Review:** If read load exceeds replica capacity, tune MV before expanding cache

## Related

- [cache-invalidation.md](../cache-invalidation.md)
- [scalability-and-async.md](../scalability-and-async.md)
