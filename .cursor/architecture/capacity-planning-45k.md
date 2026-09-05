# Capacity Planning — 45K Student Province Scenario

> **Scope:** 20 schools × 5 vocational departments × 3 stages × 3 sections × 50 students  
> **Duration:** 10 academic years  
> **Target DB:** PostgreSQL 16+

## Organizational Scale

```
20 schools (province/governorate)
  × 5 vocational departments
  × 3 stages (grade levels)
  × 3 sections (شعب)
  × 50 students
─────────────────────────────────
= 45 sections per school
= 2,250 students per school
= 45,000 active students province-wide
= 900 sections total
≈ 1,500–2,000 teachers
≈ 50,000 total system users
```

## Data Volume Projections

| Table | Formula | Per Year | 10 Years |
|-------|---------|----------|----------|
| `attendance.records` | 45K × 200 days × 5 sessions | **45M rows** | **450M rows** |
| `attendance.daily_section_summary` | 900 sections × 200 days | 180K rows | 1.8M rows |
| `exams.student_grades` | 45K × 15 subjects × 4 assessments | 2.7M rows | 27M rows |
| `enrollment.enrollments` | 45K new/year | 45K rows | 450K rows |
| `audit.audit_logs` | ~5% of write ops | ~2M rows | 20M rows |
| `students.students` (unique) | cumulative with turnover | +45K/year | ~60K unique |

## Storage Estimates (10 Years)

| Component | Raw Data | With Indexes | Notes |
|-----------|----------|-------------|-------|
| attendance.records | ~40 GB | **~160 GB** | Largest table — must partition |
| student_grades | ~2 GB | ~10 GB | Partition by academic_year_id |
| daily_section_summary | ~200 MB | ~500 MB | Dashboard reads |
| All other tables | ~5 GB | ~15 GB | Reference + transactional |
| audit logs | ~5 GB | ~20 GB | BRIN + partition by month |
| **Total** | | **~200–250 GB** | Plan 500 GB disk |

## Peak Load Profile

### Morning Attendance Window (8:00–8:30)

```
900 teachers × 50 students = 45,000 attendance records
Time window: 30 minutes
Average write rate: ~25/sec
Peak burst: 100–200/sec
```

**Required:** Batch INSERT (500 rows/batch), dedicated queue workers, partitioning.

### Concurrent Users

| Period | Users | Activity |
|--------|-------|----------|
| Morning peak | 500–2,000 | Attendance entry |
| School day | 200–800 | Grades, search, profiles |
| Exam period | 1,000–3,000 | Grade entry, reports |
| End of year | 500–1,500 | Promotion, certificates |

## Infrastructure Sizing (Production)

| Component | Specification | Rationale |
|-----------|--------------|-----------|
| PostgreSQL Primary | 16 vCPU, 32 GB RAM, 500 GB NVMe | 450M attendance rows + indexes |
| Read Replica | 8 vCPU, 16 GB RAM, 500 GB SSD | Reports + dashboards |
| Redis | 4 GB RAM | Cache + queue |
| App Servers (×2) | 4 vCPU, 8 GB RAM each | Laravel + Horizon workers |
| PgBouncer | On app tier | 2000 HTTP → 100 DB connections |

## SLA Targets

| Operation | P95 Target | Strategy |
|-----------|-----------|----------|
| Login | < 300ms | Session cache |
| Search student | < 200ms | Partial + covering index |
| Student profile | < 400ms | Eager load |
| Mark attendance (50 students) | < 2s | Batch insert |
| School dashboard | < 1s | daily_section_summary + MV |
| Directorate report (20 schools) | < 3s | Read replica + MV |

## Mandatory Optimizations for This Scale

| # | Optimization | Priority | Reason |
|---|-------------|----------|--------|
| 1 | Partition attendance.records by academic_year_id | **P0** | 45M rows/year |
| 2 | daily_section_summary table | **P0** | Avoid scanning 45K rows per dashboard |
| 3 | Batch write pattern for attendance | **P0** | 45K inserts in 30 min |
| 4 | Read Replica from launch | **P0** | Report load separation |
| 5 | PgBouncer from launch | **P0** | Connection pooling |
| 6 | RLS by school_id | **P0** | 20-school isolation |
| 7 | Materialized views for directorate | **P0** | 20-school aggregates |
| 8 | PostgreSQL tuning (see postgresql-tuning.md) | **P0** | Hardware utilization |

## Growth Beyond 10 Years

At same enrollment rate for years 11–20:
- attendance.records: **900M rows** (~320 GB indexed)
- Strategy: DETACH old partitions → archive storage
- Years 1–10 remain queryable on primary (WARM tier)

## Load Testing Requirements

Before production, test with simulated data:

| Test | Target | Pass Criteria |
|------|--------|---------------|
| 500 concurrent logins | 5 min | P95 < 500ms |
| 900 simultaneous attendance batches | 30 min | All complete, no deadlocks |
| 45K attendance insert batch | Single job | < 60 seconds |
| Directorate dashboard | 20 schools | P95 < 3s |
| 10-year attendance query (single student) | 1 student | P95 < 200ms with partition pruning |
