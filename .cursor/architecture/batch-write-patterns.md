# Batch Write Patterns

> **Mandatory for:** Attendance, bulk import, mass grade entry, certificate generation  
> **Scale context:** 45,000 students baseline — see [capacity-planning.md](./capacity-planning.md)

## Rule

Never insert high-volume transactional data row-by-row inside HTTP requests or loops.

```
❌ 45,000 individual INSERT statements
✅ 90 batch INSERTs of 500 rows each
✅ PostgreSQL COPY for imports > 10,000 rows
```

## Pattern 1 — Laravel Batch Upsert (Attendance via CQRS Infrastructure)

**Authoritative Attendance write path:** `MarkSectionAttendanceHandler` →
`AttendanceWriteRepositoryInterface::upsertAttendanceRecords` (chunk 500) +
`refreshDailySectionSummary` using **`session.session_date`** (not calendar `today()`).

```php
// Infrastructure adapter called ONLY from Application/Attendance handlers

public function upsertAttendanceRecords(array $rows): int
{
    foreach (array_chunk($payload, 500) as $chunk) {
        DB::table('attendance.records')->upsert(
            $chunk,
            uniqueBy: ['session_id', 'student_id', 'academic_year_id'],
            update: ['status', 'notes', 'recorded_by', 'updated_at'],
        );
    }

    return count($rows);
}
```

### Legacy note (R1.9 Option B)

`app/Services/Attendance/AttendanceBatchService` is **deprecated/quarantined**.
It must not be used as an alternate public Attendance writer.
Runtime calls fail closed. Full class deletion requires separate human authorization.

## Pattern 2 — PostgreSQL COPY (Bulk Import)

For imports > 10,000 rows (student enrollment, historical data):

```php
public function importFromCsv(string $filePath, string $table): void
{
    $qualified = SchemaHelper::qualified('students', 'students');

    DB::statement("
        COPY {$qualified} (student_code, first_name, last_name, full_name, gender, birth_date, status, created_at, updated_at)
        FROM '{$filePath}'
        WITH (FORMAT csv, HEADER true)
    ");

    DB::statement("ANALYZE {$qualified}");
}
```

Run COPY inside a **queue job**, not HTTP request.

## Pattern 3 — Upsert for Idempotent Writes

```php
DB::table('attendance.records')->upsert(
    $rows,
    uniqueBy: ['session_id', 'student_id'],
    update: ['status', 'notes', 'updated_at'],
);
```

Prevents duplicate attendance if teacher retries submission.

## Pattern 4 — Queue Job for Large Batches

```php
// Dispatch when section attendance > 50 or bulk operation
RecordSectionAttendanceJob::dispatch($sessionId, $records)
    ->onQueue('attendance-writes');
```

## Batch Sizes

| Operation | Batch Size | Queue |
|-----------|-----------|-------|
| Section attendance (50 students) | 500 | attendance-writes |
| School-wide attendance | 500/chunk | attendance-writes |
| Student import | 500/chunk | imports |
| Grade entry | 200/chunk | grades |
| Certificate generation | 50/chunk | certificates |

## Post-Batch Maintenance

After any bulk operation > 10,000 rows:

```sql
ANALYZE attendance.records;
ANALYZE attendance.daily_section_summary;
```

Schedule via job completion callback, not manually in production.

## Controller Pattern

```php
// ❌ BAD
public function store(Request $request) {
    foreach ($request->students as $student) {
        AttendanceRecord::create([...]);
    }
}

// ✅ GOOD — HTTP → Application CQRS handler (not legacy Services)
public function mark(MarkSectionAttendanceRequest $request, MarkSectionAttendanceHandler $handler) {
    $result = $handler->handle(/* MarkSectionAttendanceCommand */);
    return response()->json(['data' => ['marked_count' => $result->markedCount]]);
}
```

## Daily Summary Refresh

After batch attendance insert, update summary table (not full recalculation):

```php
private function refreshDailySummary(int $sessionId, string $date): void
{
    DB::statement("
        INSERT INTO attendance.daily_section_summary
            (section_id, school_id, academic_year_id, attendance_date,
             total_students, present_count, absent_count, late_count, updated_at)
        SELECT
            s.section_id,
            e.school_id,
            e.academic_year_id,
            ?,
            COUNT(*),
            COUNT(*) FILTER (WHERE r.status = 1),
            COUNT(*) FILTER (WHERE r.status = 2),
            COUNT(*) FILTER (WHERE r.status = 3),
            NOW()
        FROM attendance.records r
        JOIN attendance.sessions s ON s.id = r.session_id
        JOIN enrollment.enrollments e ON e.id = r.enrollment_id
        WHERE s.id = ? AND r.attendance_date = ?
        GROUP BY s.section_id, e.school_id, e.academic_year_id
        ON CONFLICT (section_id, attendance_date)
        DO UPDATE SET
            total_students = EXCLUDED.total_students,
            present_count  = EXCLUDED.present_count,
            absent_count   = EXCLUDED.absent_count,
            late_count     = EXCLUDED.late_count,
            updated_at     = NOW()
    ", [$date, $sessionId, $date]);
}
```
