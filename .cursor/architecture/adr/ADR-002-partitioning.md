# ADR-002: Partition attendance and grades by Academic Year

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Partition `attendance.records` and `exams.student_grades` by **`academic_year_id` (LIST)** from year one — not deferred until 10M rows.

## Why

```
45,000 students × 200 days × 5 sessions = ~45M attendance rows/year
10 years = ~450M rows
```

Without partitioning:
- Queries scan hundreds of millions of rows
- Index maintenance and autovacuum degrade
- Backup/restore times increase

Academic year matches query patterns:
- Teachers query current year 95%+ of time
- Reports scoped by year
- Archive = detach old year partition

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| No partition | Proven failure at 450M rows |
| Monthly RANGE on date | Academic queries use year_id, not month |
| Hash partition | No partition pruning for year filters |
| Separate DB per school | 20× operational overhead |

## Consequences

- Must CREATE new partition before each academic year
- FK to partitioned tables requires careful design (PK includes partition key)
- Migrations more complex — see zero-downtime-migrations.md
- **Review:** If cross-year analytics dominate, add MV instead of cross-partition scans

## Related

- [capacity-planning-45k.md](../capacity-planning-45k.md)
- [phases/PHASE-C-OPERATIONS.md](../phases/PHASE-C-OPERATIONS.md)
