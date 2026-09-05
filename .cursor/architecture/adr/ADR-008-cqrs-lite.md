# ADR-008: CQRS-Lite — Not Full CQRS/Microservices

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Apply **CQRS-lite** in Laravel application layer:
- **Commands** (Actions/Services) for writes
- **Queries** (Query classes) for reads from summary/MV/replica

Do **NOT** implement:
- Separate write/read databases
- Event sourcing for all domains
- Kafka/message bus
- Microservices split

## Why

```
45K students / 20 schools
```

Monolith + PostgreSQL + Redis + Queue + Replica handles this scale with far less ops cost.

Full CQRS justified when:
- Independent team per service
- 10× current write throughput
- Different storage engines per read model

None apply now.

## Implementation

```
EnrollStudentAction          SchoolDashboardQuery
RecordAttendanceBatch        StudentHistoryQuery
EnterGradesBatch             DirectorateReportQuery
```

Read models: `daily_section_summary`, materialized views, Redis cache.

## Consistency

| Layer | Model |
|-------|-------|
| Writes | Strong (PostgreSQL transaction) |
| Summary tables | Eventual (< 5s) |
| MV | Eventual (minutes–hours) |

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Full CQRS + event store | Over-engineering |
| Microservices | Ops cost; team size |
| No separation | Fat services mixing read/write optimization |

## Consequences

- Developers must know which side they're implementing
- Query classes must not mutate state
- **Review:** If attendance write P95 degrades, optimize commands before splitting services

## Related

- [normalization-and-cqrs.md](../normalization-and-cqrs.md)
- [laravel-architecture.md](../laravel-architecture.md)
