# Peak Hour Strategy — Morning Attendance Window

> **Critical window:** 8:00–8:30 AM school days  
> **Load:** 900 teachers × 50 students = 45,000 writes in 30 minutes

## Problem

Without strategy, morning attendance causes:
- Connection storms (900 simultaneous requests)
- Row-by-row INSERT bottleneck
- Lock contention on attendance.records
- Slow dashboards querying raw 45K rows

## Architecture for Peak Window

```
Teacher submits attendance (50 students)
        ↓
Controller validates + returns 202 Accepted immediately
        ↓
RecordSectionAttendanceJob → queue: attendance-writes
        ↓
AttendanceBatchService::recordSectionAttendance (500/chunk)
        ↓
attendance.records (partitioned by academic_year_id)
        ↓
refreshDailySummary() → attendance.daily_section_summary
        ↓
WebSocket/poll: "Attendance saved ✓"
```

## Queue Configuration

```php
// config/horizon.php (when Horizon added)
'defaults' => [
    'attendance-writes' => [
        'connection' => 'redis',
        'queue' => ['attendance-writes'],
        'balance' => 'auto',
        'processes' => 8,        // 8 workers for peak window
        'tries' => 3,
        'timeout' => 120,
    ],
],
```

Morning scale-up (via scheduler 7:55 AM):

```
8 workers → attendance-writes queue (7:55–9:00)
4 workers → normal (rest of day)
```

## Rate Limiting

Prevent duplicate submissions from double-click:

```php
// routes — throttle per teacher
Route::post('/attendance/sections/{section}/batch', ...)
    ->middleware(['auth', 'throttle:12,1']); // max 12 requests/minute per teacher
```

Idempotency via upsert on `(session_id, student_id)`.

## Read Path During Peak

Dashboards during 8:00–8:30 must NOT query `attendance.records`:

```
❌ SELECT COUNT(*) FROM attendance.records WHERE date = today  -- scans millions
✅ SELECT * FROM attendance.daily_section_summary WHERE date = today  -- 900 rows
✅ SELECT * FROM reports.mv_daily_attendance WHERE date = today  -- materialized
```

## Timeline

| Time | Action | Workers |
|------|--------|---------|
| 7:55 | Scale queue workers to 8 | Horizon |
| 8:00–8:30 | Peak attendance writes | 8 workers |
| 8:30 | Refresh mv_daily_attendance | 1 worker |
| 9:00 | Scale down to 4 workers | Horizon |
| 23:00 | Nightly MV refresh all reports | Scheduler |

## PostgreSQL During Peak

- All writes go to **Primary** (never replica)
- PgBouncer pool_mode = **transaction** (release connection fast)
- No long-running reports during 8:00–8:30
- Schedule ANALYZE for off-peak only

## Failure Handling

```php
RecordSectionAttendanceJob::dispatch(...)
    ->onQueue('attendance-writes')
    ->retry(3)
    ->backoff([10, 30, 60]);
```

Failed jobs → `failed_jobs` table → admin notification.

Teacher UI shows:
- `pending` — job queued
- `processing` — worker active
- `completed` — success with count
- `failed` — retry button (uses same idempotency key)

## Capacity Check

```
45,000 records ÷ 500 batch = 90 batch operations
8 workers × 2 batches/sec = 16 batches/sec
90 ÷ 16 = ~6 seconds total system throughput

Bottleneck is NOT database — it's queue worker count.
Ensure 8+ workers during peak.
```

## Monitoring Alerts (Peak Window)

| Metric | Threshold | Action |
|--------|-----------|--------|
| Queue depth (attendance-writes) | > 100 jobs | Add workers |
| Job processing time P95 | > 5s | Check DB locks |
| Failed jobs | > 0 | Alert admin |
| DB connections | > 80% max | Check PgBouncer |
| daily_summary lag | > 5 min behind | Trigger manual refresh |
