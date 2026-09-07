<?php

/** @architecture-legacy-allowed migrate to Application/Attendance */

namespace App\Services\Attendance;

use App\Database\SchemaHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceBatchService
{
    /**
     * @param  list<array{student_id: int, enrollment_id: int, status: int, notes?: string|null}>  $records
     */
    public function recordSectionAttendance(
        int $sessionId,
        int $sectionId,
        int $academicYearId,
        int $schoolId,
        array $records,
        int $recordedBy,
    ): int {
        $table = SchemaHelper::qualified('attendance', 'records');
        $now = now();
        $date = today()->toDateString();

        $rows = collect($records)->map(fn (array $record) => [
            'session_id' => $sessionId,
            'student_id' => $record['student_id'],
            'enrollment_id' => $record['enrollment_id'],
            'academic_year_id' => $academicYearId,
            'school_id' => $schoolId,
            'attendance_date' => $date,
            'status' => $record['status'],
            'notes' => $record['notes'] ?? null,
            'recorded_by' => $recordedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $inserted = 0;

        /** @var Collection<int, array<string, mixed>> $chunk */
        foreach ($rows->chunk(500) as $chunk) {
            DB::table($table)->upsert(
                $chunk->all(),
                SchemaHelper::isPostgreSql()
                    ? ['session_id', 'student_id', 'academic_year_id']
                    : ['session_id', 'student_id'],
                ['status', 'notes', 'recorded_by', 'updated_at'],
            );
            $inserted += $chunk->count();
        }

        $this->refreshDailySummary($sectionId, $schoolId, $academicYearId, $date);

        return $inserted;
    }

    public function refreshDailySummary(
        int $sectionId,
        int $schoolId,
        int $academicYearId,
        string $date,
    ): void {
        if (! SchemaHelper::isPostgreSql()) {
            $this->refreshDailySummarySqlite($sectionId, $schoolId, $academicYearId, $date);

            return;
        }

        $records = SchemaHelper::qualified('attendance', 'records');
        $sessions = SchemaHelper::qualified('attendance', 'sessions');
        $summary = SchemaHelper::qualified('attendance', 'daily_section_summary');

        DB::statement("
            INSERT INTO {$summary}
                (section_id, school_id, academic_year_id, attendance_date,
                 total_students, present_count, absent_count, late_count, updated_at)
            SELECT
                ?,
                ?,
                ?,
                ?::date,
                COUNT(*)::SMALLINT,
                COUNT(*) FILTER (WHERE r.status = 1)::SMALLINT,
                COUNT(*) FILTER (WHERE r.status = 2)::SMALLINT,
                COUNT(*) FILTER (WHERE r.status = 3)::SMALLINT,
                NOW()
            FROM {$records} r
            JOIN {$sessions} s ON s.id = r.session_id
            WHERE s.section_id = ? AND r.attendance_date = ?::date
            ON CONFLICT (section_id, attendance_date)
            DO UPDATE SET
                total_students = EXCLUDED.total_students,
                present_count  = EXCLUDED.present_count,
                absent_count   = EXCLUDED.absent_count,
                late_count     = EXCLUDED.late_count,
                updated_at     = NOW()
        ", [$sectionId, $schoolId, $academicYearId, $date, $sectionId, $date]);
    }

    private function refreshDailySummarySqlite(
        int $sectionId,
        int $schoolId,
        int $academicYearId,
        string $date,
    ): void {
        $records = SchemaHelper::qualified('attendance', 'records');
        $sessions = SchemaHelper::qualified('attendance', 'sessions');
        $summary = SchemaHelper::qualified('attendance', 'daily_section_summary');

        $stats = DB::table("{$records} as r")
            ->join("{$sessions} as s", 's.id', '=', 'r.session_id')
            ->where('s.section_id', $sectionId)
            ->where('r.attendance_date', $date)
            ->selectRaw('
                COUNT(*) as total_students,
                SUM(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN r.status = 2 THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN r.status = 3 THEN 1 ELSE 0 END) as late_count
            ')
            ->first();

        DB::table($summary)->updateOrInsert(
            ['section_id' => $sectionId, 'attendance_date' => $date],
            [
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'total_students' => (int) ($stats->total_students ?? 0),
                'present_count' => (int) ($stats->present_count ?? 0),
                'absent_count' => (int) ($stats->absent_count ?? 0),
                'late_count' => (int) ($stats->late_count ?? 0),
                'updated_at' => now(),
            ],
        );
    }
}
