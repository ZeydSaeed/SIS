# ADR-006: Read Replica from Production Launch

**Status:** Accepted (45K scenario override)  
**Date:** 2026-09-05

## Decision

Deploy **PostgreSQL Read Replica** from production launch — not deferred to Phase 2 after pain.

## Why

- 20 school dashboards + directorate reports compete with OLTP writes
- Morning attendance peak: 45K writes must not block report readers
- Replica lag acceptable for dashboards (eventual consistency)

## Read Routing

| Operation | Target |
|-----------|--------|
| Writes | Primary |
| Dashboards, reports, search | Replica |
| Post-write immediate read | Primary |

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Single node until pain | Reports will slow attendance peak |
| Reporting DB immediately | Over-engineering for launch |
| MV only, no replica | MV refresh still hits Primary |

## Consequences

- Must monitor replication lag (alert > 30s)
- Laravel read/write connection config or manual replica routing
- **Review:** If lag consistently > 60s, investigate WAL or add second replica

## Related

- [02-infrastructure-phases.md](../02-infrastructure-phases.md)
- [production-readiness.md](../production-readiness.md)
