# Load Test Results — Template

> **Scenario:** 45K students, 20 schools, 900 sections  
> **Environment:** Staging (must mirror production hardware)  
> **Reference:** [capacity-planning-45k.md](./capacity-planning-45k.md)

## Test Environment

| Item | Value |
|------|-------|
| Date | _not yet run_ |
| PostgreSQL | _version_ |
| Hardware | _spec_ |
| Data volume | _enrollments / attendance rows_ |
| Tool | _k6 / Artillery / Laravel Dusk_ |

## Results

| # | Scenario | Concurrent | P50 | P95 | P99 | Pass? | Notes |
|---|----------|-----------|-----|-----|-----|-------|-------|
| 1 | Login | 500 | — | — | — | ⏳ | Target P95 < 500ms |
| 2 | Student search | 200 | — | — | — | ⏳ | Target P95 < 200ms |
| 3 | Attendance batch (50 students) | 900 | — | — | — | ⏳ | All complete < 2s |
| 4 | 45K bulk attendance insert | 1 job | — | — | — | ⏳ | Target < 60s |
| 5 | School dashboard | 20 | — | — | — | ⏳ | Target P95 < 1s |
| 6 | Directorate dashboard | 5 | — | — | — | ⏳ | Target P95 < 3s |
| 7 | Stress test | 2000 | — | — | — | ⏳ | Document breaking point |
| 8 | 10-year attendance query (1 student) | 50 | — | — | — | ⏳ | Partition pruning, P95 < 200ms |

## Observations

_Space for notes after testing: slow queries, deadlocks, connection limits, queue depth._

## EXPLAIN ANALYZE — Partition Pruning

```sql
-- Paste EXPLAIN output here after test 8
EXPLAIN ANALYZE
SELECT * FROM attendance.records
WHERE academic_year_id = 1
  AND student_id = 12345
ORDER BY attendance_date;
```

**Expected:** Scan on `records_y2025` partition only — not full table.

## Sign-Off

| Role | Name | Date | Approved |
|------|------|------|----------|
| Tech Lead | | | ⏳ |
| DBA | | | ⏳ |

## Next Actions

- [ ] Run tests before production (Phase F)
- [ ] Record results in this file
- [ ] Fix failures before go-live
