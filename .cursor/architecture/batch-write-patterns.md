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

## Pattern 1 — Laravel Batch Insert

```php
// app/Services/Attendance/AttendanceBatchService.php

public function recordSectionAttendance(
    int $sessionId,
    int $academicYearId,
    int $schoolId,
    array $records, // [['student_id' => 1, 'enrollment_id' => 2, 'status' => 1], ...]
    int $recordedBy,
): int {
    $now = now();
    $date = today()->toDateString();

    $rows = collect($records)->map(fn (array $r) => [
        'session_id'       => $sessionId,
        'student_id'       => $r['student_id'],
        'enrollment_id'    => $r['enrollment_id'],
        'academic_year_id' => $academicYearId,
        'school_id'        => $schoolId,
        'attendance_date'  => $date,
        'status'           => $r['status'],
        'recorded_by'      => $recordedBy,
        'created_at'       => $now,
        'updated_at'       => $now,
    ]);

    $inserted = 0;
    foreach ($rows->chunk(500) as $chunk) {
        DB::table('attendance.records')->insertOrIgnore($chunk->all());
        $inserted += $chunk->count();
    }

    $this->refreshDailySummary($sessionId, $date);

    return $inserted;
}
```

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

// ✅ GOOD
public function store(AttendanceBatchRequest $request, AttendanceBatchService $service) {
    $count = $service->recordSectionAttendance(...);
    return back()->with('success', "{$count} records saved.");
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
