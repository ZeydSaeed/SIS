# Phase C — Daily Operations

> **الحالة:** دليل مرجع — لا migration  
> **المتطلب:** Phase B مكتمل (45K enrollments)  
> **التالي:** [PHASE-D-LIFECYCLE.md](./PHASE-D-LIFECYCLE.md)

## الهدف

حضور يومي، جداول، تقارير — **أعلى ضغط على النظام** (45M صف حضور/سنة).

## الجداول (3 + 8 views)

| Schema | الجداول |
|--------|---------|
| timetable | periods |
| attendance | sessions, records (partitioned), daily_section_summary |
| reports | 5 MV + 3 directorate MV |

## 1. attendance.records — Partitioned P0

**لا تنتظر 10M صف — partition من أول migration.**

```sql
CREATE TABLE attendance.records (...)
PARTITION BY LIST (academic_year_id);

CREATE TABLE attendance.records_y2025
    PARTITION OF attendance.records
    FOR VALUES IN (1);
```

راجع `database-blueprint.md` → attendance.records.

## 2. attendance.daily_section_summary — P0

```
900 sections × 200 days = 180K rows/year
Dashboard يقرأ 900 صف بدلاً من 45,000
```

**PK:** `(section_id, attendance_date)`

## 3. AttendanceBatchService

**إلزامي** — راجع `batch-write-patterns.md`:

```
Teacher → Controller (202 Accepted) → Queue → Batch 500/chunk → daily_summary refresh
```

## 4. Peak Hour (8:00–8:30)

راجع `peak-hour-strategy.md`:

- 8 queue workers on `attendance-writes`
- Rate limit: 12 req/min per teacher
- Upsert on `(session_id, student_id)`

## 5. Materialized Views

| View | Refresh | القراءة |
|------|---------|---------|
| mv_school_student_statistics | nightly | مدير المدرسة |
| mv_daily_attendance | hourly (8–14) | dashboard |
| mv_directorate_school_comparison | nightly | مديرية (20 مدرسة) |
| mv_section_attendance_weekly | nightly | مشرف |
| mv_vocational_department_stats | nightly | أقسام مهنية |

```sql
REFRESH MATERIALIZED VIEW CONCURRENTLY reports.mv_daily_attendance;
```

## 6. timetable.periods

```
periods per school (6–8 periods/day)
schedules → Phase C+ (when UI ready)
```

## Load Test — قبل Phase D

| Test | Pass |
|------|------|
| 900 teachers submit 50-student batch | All complete < 2s |
| 45K bulk insert job | < 60 seconds |
| School dashboard | P95 < 1s |
| Directorate dashboard (20 schools) | P95 < 3s |
| 10-year student attendance query | Partition pruning verified |

سجّل النتائج في `load-test-results.md`.

## Checklist قبل Phase D

- [ ] attendance.records partitioned by academic_year_id
- [ ] daily_section_summary updates after each batch
- [ ] No row-by-row attendance INSERT in controllers
- [ ] MV refresh scheduled (not in HTTP)
- [ ] Peak hour: 8 workers configured
- [ ] EXPLAIN ANALYZE: partition pruning on attendance query
- [ ] database-blueprint.md synced

## مراجع

- `batch-write-patterns.md`
- `peak-hour-strategy.md`
- `capacity-planning.md`
- `indexing-matrix.md` → attendance section
