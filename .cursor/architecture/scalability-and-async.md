# Scalability, Caching & Async Operations

## Redis Caching

**Rule:** PostgreSQL is source of truth. Redis is cache only.

### What to Cache

| Data | TTL | Invalidation |
|------|-----|-------------|
| Academic years, terms | 4–24 hours | Admin update |
| Schools, branches, rooms | 4–24 hours | Admin update |
| Subjects, curriculum | 1–4 hours | Curriculum change |
| Roles, permissions | 1–4 hours | Security update |
| Dashboard aggregates | 5–15 minutes | Scheduled refresh |
| System settings | 1 hour | Settings update |

### What NOT to Cache as Primary Read

- Individual grades and attendance records
- Financial transactions
- Real-time enrollment status after writes
- Audit logs

### Laravel Cache Pattern

```php
// Reference data
Cache::remember('academic_years.active', 3600, fn () =>
    AcademicYear::where('status', 1)->get()
);

// Invalidate on update
Cache::forget('academic_years.active');
```

## Async Queue Operations

Never block HTTP requests for these:

| Operation | Queue Job | Progress UI |
|-----------|-----------|--------------|
| Bulk certificate generation | `GenerateCertificatesJob` | Percentage bar |
| Mass SMS/email notifications | `SendBulkNotificationsJob` | Sent count |
| Student bulk import (1000+) | `ImportStudentsJob` | Row count |
| Excel/PDF report export | `GenerateReportJob` | Download link |
| Materialized view refresh | `RefreshMaterializedViewJob` | Last refreshed |
| Transcript batch generation | `GenerateTranscriptsJob` | Count |

### Job Pattern

```
User action
    ↓
Create job record (with idempotency_key)
    ↓
Dispatch to Laravel Queue (Redis driver)
    ↓
Worker processes in background
    ↓
Update job status + notify user (WebSocket/polling)
```

### Idempotency

```php
// Prevent duplicate certificate generation
CertificateGenerationJob::dispatch($params)
    ->withIdempotencyKey("cert-{$schoolId}-{$yearId}-{$batchId}");
```

## Materialized Views

Pre-compute heavy aggregations. Dashboard reads from views, not OLTP joins.

| View | Refresh Frequency | Source Tables |
|------|------------------|---------------|
| mv_school_student_statistics | Nightly | enrollments, students |
| mv_daily_attendance | Hourly during school day | attendance.records |
| mv_subject_results | After exam period closes | student_grades |
| mv_academic_performance | Nightly | annual_results, attendance |
| mv_graduation_statistics | On graduation event | graduation.records |
| mv_directorate_school_comparison | Nightly | 20-school comparison (45K) |
| mv_section_attendance_weekly | Nightly | Section weekly stats |
| mv_vocational_department_stats | Nightly | 5 departments × 20 schools |

```sql
REFRESH MATERIALIZED VIEW CONCURRENTLY reports.mv_school_student_statistics;
```

Schedule via Laravel Queue + cron, not inside user requests.

## Read Replica Usage

| Operation | Target |
|-----------|--------|
| INSERT, UPDATE, DELETE | Primary |
| Dashboard reads | Replica (acceptable lag) |
| Report generation | Replica |
| Student search | Replica |
| Post-write immediate read | Primary |

## Reporting Architecture

```
❌ Wrong:
Principal Dashboard → JOIN 20 tables → Production DB → Slow

✅ Correct:
Production DB → Background Job → Materialized View → Dashboard
```

## Bulk Import Pattern

```
Upload CSV/Excel
    ↓
Validate in memory (sample rows)
    ↓
Create import job with idempotency_key
    ↓
Worker: COPY / batch INSERT (500 rows per batch)
    ↓
Log errors per row → error report downloadable
    ↓
ANALYZE affected tables
```

## Connection Limits

```
max_connections = calculated from load testing
NOT max_connections = 5000 "just in case"

App processes × pool size → PgBouncer → controlled DB connections
```
