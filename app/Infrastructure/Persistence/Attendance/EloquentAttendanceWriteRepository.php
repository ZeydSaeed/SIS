<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Database\SchemaHelper;
use App\Domain\Attendance\Data\AttendanceRecordSnapshot;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Attendance\Data\UpsertAttendanceRecordData;
use App\Domain\Attendance\Exceptions\DuplicateOpenAttendanceSessionException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceWriteRepository implements AttendanceWriteRepositoryInterface
{
    public function resolveSchoolIdForSection(int $sectionId): ?int
    {
        $sections = SchemaHelper::qualified('enrollment', 'sections');
        $classes = SchemaHelper::qualified('enrollment', 'classes');

        $schoolId = DB::table("{$sections} as s")
            ->join("{$classes} as c", 'c.id', '=', 's.class_id')
            ->where('s.id', $sectionId)
            ->value('c.school_id');

        return $schoolId !== null ? (int) $schoolId : null;
    }

    public function academicYearContainsDate(int $academicYearId, string $date): bool
    {
        $years = SchemaHelper::qualified('academic', 'academic_years');

        return DB::table($years)
            ->where('id', $academicYearId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    public function periodBelongsToSchool(int $periodId, int $schoolId): bool
    {
        $periods = SchemaHelper::qualified('timetable', 'periods');

        return DB::table($periods)
            ->where('id', $periodId)
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function findDuplicateOpenSession(
        int $schoolId,
        int $academicYearId,
        int $sectionId,
        int $subjectId,
        string $sessionDate,
        ?int $periodId,
    ): ?int {
        $query = DB::table($this->sessionsTable())
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('session_date', $sessionDate)
            ->where('status', SessionStatus::Open->value);

        if ($periodId === null) {
            $query->whereNull('period_id');
        } else {
            $query->where('period_id', $periodId);
        }

        $id = $query->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function insertSession(
        int $sectionId,
        int $subjectId,
        int $academicYearId,
        string $sessionDate,
        ?int $periodId,
        int $teacherId,
        int $status,
        int $schoolId,
    ): int {
        try {
            return (int) DB::table($this->sessionsTable())->insertGetId([
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'academic_year_id' => $academicYearId,
                'session_date' => $sessionDate,
                'period_id' => $periodId,
                'teacher_id' => $teacherId,
                'status' => $status,
                'school_id' => $schoolId,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if ($status === SessionStatus::Open->value) {
                throw DuplicateOpenAttendanceSessionException::forNaturalKey(
                    $schoolId,
                    $academicYearId,
                    $sectionId,
                    $subjectId,
                    $sessionDate,
                    $periodId,
                );
            }

            throw $e;
        }
    }

    public function findSessionById(int $sessionId): ?AttendanceSessionSnapshot
    {
        return $this->mapSessionRow($this->sessionQuery()->where('sess.id', $sessionId)->first());
    }

    public function lockSessionStatus(int $sessionId): ?AttendanceSessionSnapshot
    {
        return $this->mapSessionRow($this->sessionQuery()->where('sess.id', $sessionId)->first());
    }

    public function loadEnrollmentForMark(int $enrollmentId): ?EnrollmentAttendanceContext
    {
        $enrollments = SchemaHelper::qualified('enrollment', 'enrollments');

        $row = DB::table($enrollments)
            ->select([
                'id',
                'student_id',
                'school_id',
                'academic_year_id',
                'section_id',
                'effective_from',
                'effective_to',
                'status',
            ])
            ->where('id', $enrollmentId)
            ->first();

        if ($row === null) {
            return null;
        }

        return new EnrollmentAttendanceContext(
            id: (int) $row->id,
            studentId: (int) $row->student_id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            sectionId: (int) $row->section_id,
            effectiveFrom: (string) $row->effective_from,
            effectiveTo: $row->effective_to !== null ? (string) $row->effective_to : null,
            status: (int) $row->status,
        );
    }

    public function upsertAttendanceRecords(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $payload = array_map(static fn (UpsertAttendanceRecordData $row): array => [
            'session_id' => $row->sessionId,
            'student_id' => $row->studentId,
            'enrollment_id' => $row->enrollmentId,
            'academic_year_id' => $row->academicYearId,
            'school_id' => $row->schoolId,
            'attendance_date' => $row->attendanceDate,
            'status' => $row->status,
            'notes' => $row->notes,
            'recorded_by' => $row->recordedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        $unique = SchemaHelper::isPostgreSql()
            ? ['session_id', 'student_id', 'academic_year_id']
            : ['session_id', 'student_id'];

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table($this->recordsTable())->upsert(
                $chunk,
                $unique,
                ['status', 'notes', 'recorded_by', 'updated_at'],
            );
        }

        return count($rows);
    }

    public function findRecord(
        int $sessionId,
        int $studentId,
        int $academicYearId,
        int $schoolId,
    ): ?AttendanceRecordSnapshot {
        $row = DB::table($this->recordsTable())
            ->select([
                'id',
                'session_id',
                'student_id',
                'enrollment_id',
                'academic_year_id',
                'school_id',
                'attendance_date',
                'status',
                'notes',
                'recorded_by',
            ])
            ->where('session_id', $sessionId)
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->first();

        if ($row === null) {
            return null;
        }

        return new AttendanceRecordSnapshot(
            id: (int) $row->id,
            sessionId: (int) $row->session_id,
            studentId: (int) $row->student_id,
            enrollmentId: (int) $row->enrollment_id,
            academicYearId: (int) $row->academic_year_id,
            schoolId: (int) $row->school_id,
            attendanceDate: (string) $row->attendance_date,
            status: (int) $row->status,
            notes: $row->notes !== null ? (string) $row->notes : null,
            recordedBy: $row->recorded_by !== null ? (int) $row->recorded_by : null,
        );
    }

    public function updateRecordStatus(
        int $recordId,
        int $academicYearId,
        int $schoolId,
        int $status,
        ?string $notes,
        ?int $recordedBy,
    ): void {
        DB::table($this->recordsTable())
            ->where('id', $recordId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->update([
                'status' => $status,
                'notes' => $notes,
                'recorded_by' => $recordedBy,
                'updated_at' => now(),
            ]);
    }

    public function closeSessionIfOpen(int $sessionId): bool
    {
        $updated = DB::table($this->sessionsTable())
            ->where('id', $sessionId)
            ->where('status', SessionStatus::Open->value)
            ->update(['status' => SessionStatus::Closed->value]);

        return $updated === 1;
    }

    public function cancelSessionIfOpenOrClosed(int $sessionId): ?int
    {
        $row = DB::table($this->sessionsTable())
            ->where('id', $sessionId)
            ->whereIn('status', [SessionStatus::Open->value, SessionStatus::Closed->value])
            ->lockForUpdate()
            ->first(['status']);

        if ($row === null) {
            return null;
        }

        $previousStatus = (int) $row->status;

        $updated = DB::table($this->sessionsTable())
            ->where('id', $sessionId)
            ->where('status', $previousStatus)
            ->update(['status' => SessionStatus::Cancelled->value]);

        if ($updated !== 1) {
            return null;
        }

        return $previousStatus;
    }

    public function refreshDailySectionSummary(
        int $sectionId,
        int $schoolId,
        int $academicYearId,
        string $attendanceDate,
    ): void {
        if (! SchemaHelper::isPostgreSql()) {
            $this->refreshDailySummarySqlite($sectionId, $schoolId, $academicYearId, $attendanceDate);

            return;
        }

        $records = $this->recordsTable();
        $sessions = $this->sessionsTable();
        $summary = $this->summaryTable();

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
                school_id      = EXCLUDED.school_id,
                academic_year_id = EXCLUDED.academic_year_id,
                updated_at     = NOW()
        ", [$sectionId, $schoolId, $academicYearId, $attendanceDate, $sectionId, $attendanceDate]);
    }

    private function refreshDailySummarySqlite(
        int $sectionId,
        int $schoolId,
        int $academicYearId,
        string $attendanceDate,
    ): void {
        $stats = DB::table("{$this->recordsTable()} as r")
            ->join("{$this->sessionsTable()} as s", 's.id', '=', 'r.session_id')
            ->where('s.section_id', $sectionId)
            ->where('r.attendance_date', $attendanceDate)
            ->selectRaw('
                COUNT(*) as total_students,
                SUM(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN r.status = 2 THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN r.status = 3 THEN 1 ELSE 0 END) as late_count
            ')
            ->first();

        DB::table($this->summaryTable())->updateOrInsert(
            ['section_id' => $sectionId, 'attendance_date' => $attendanceDate],
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

    private function sessionQuery()
    {
        $sessions = $this->sessionsTable();

        return DB::table("{$sessions} as sess")
            ->select([
                'sess.id',
                'sess.section_id',
                'sess.subject_id',
                'sess.academic_year_id',
                'sess.session_date',
                'sess.period_id',
                'sess.teacher_id',
                'sess.status',
                'sess.school_id as resolved_school_id',
            ]);
    }

    private function mapSessionRow(mixed $row): ?AttendanceSessionSnapshot
    {
        if ($row === null) {
            return null;
        }

        return new AttendanceSessionSnapshot(
            id: (int) $row->id,
            sectionId: (int) $row->section_id,
            subjectId: (int) $row->subject_id,
            academicYearId: (int) $row->academic_year_id,
            sessionDate: (string) $row->session_date,
            periodId: $row->period_id !== null ? (int) $row->period_id : null,
            teacherId: (int) $row->teacher_id,
            status: (int) $row->status,
            resolvedSchoolId: isset($row->resolved_school_id) ? (int) $row->resolved_school_id : null,
        );
    }

    private function sessionsTable(): string
    {
        return SchemaHelper::qualified('attendance', 'sessions');
    }

    private function recordsTable(): string
    {
        return SchemaHelper::qualified('attendance', 'records');
    }

    private function summaryTable(): string
    {
        return SchemaHelper::qualified('attendance', 'daily_section_summary');
    }
}
