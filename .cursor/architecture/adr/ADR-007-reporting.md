# ADR-007: Materialized Views for Directorate Reporting

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Heavy aggregations use **Materialized Views (8 total)** refreshed on schedule — not live JOINs across OLTP.

## Reporting Tiers

```
Operational CRUD     → Normalized tables
School dashboard     → daily_section_summary
Directorate compare  → Materialized Views
Deep analytics       → Replica + MV
Archive              → Detached partitions
```

## Why

Directorate comparing 20 schools with live JOIN on 45M attendance rows = unacceptable P95.

## Refresh Strategy

| MV | Frequency |
|----|-----------|
| mv_daily_attendance | Hourly (school hours) |
| mv_school_student_statistics | Nightly |
| mv_directorate_school_comparison | Nightly |
| mv_subject_results | After exam period |

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Live queries | Too slow at scale |
| Separate warehouse (Year 1) | Cost/complexity |
| Cache-only | Stale + no SQL analytics |

## Consequences

- Dashboards show `refreshed_at` — eventual consistency
- `REFRESH CONCURRENTLY` requires unique indexes on MV
- **Review:** If real-time directorate view required, add summary table + event-driven update

## Related

- [scalability-and-async.md](../scalability-and-async.md)
- [normalization-and-cqrs.md](../normalization-and-cqrs.md)
