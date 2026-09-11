<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Attendance\DTOs\AttendanceRecordDTO;
use App\Application\Attendance\DTOs\AttendanceSessionDTO;
use App\Application\Attendance\DTOs\DailySectionSummaryDTO;
use App\Application\Attendance\DTOs\SectionAttendanceDTO;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceReadRepository implements AttendanceReadRepositoryInterface
{
    public function getSession(int $schoolId, int $sessionId, bool $includeRecords = false): ?AttendanceSessionDTO
    {
        $row = $this->sessionBaseQuery($schoolId)
            ->where('sess.id', $sessionId)
            ->first();

        if ($row === null) {
            return null;
        }

        $dto = $this->mapSession($row);

        if ($includeRecords) {
            $records = DB::table($this->recordsTable())
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
                ->where('school_id', $schoolId)
                ->orderBy('student_id')
                ->get()
                ->map(fn ($r): AttendanceRecordDTO => $this->mapRecord($r))
                ->all();

            return new AttendanceSessionDTO(
                id: $dto->id,
                schoolId: $dto->schoolId,
                sectionId: $dto->sectionId,
                subjectId: $dto->subjectId,
                academicYearId: $dto->academicYearId,
                sessionDate: $dto->sessionDate,
                periodId: $dto->periodId,
                teacherId: $dto->teacherId,
                status: $dto->status,
                records: $records,
            );
        }

        return $dto;
    }

    public function listSessions(
        int $schoolId,
        int $academicYearId,
        ?int $sectionId,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $status,
        int $page,
        int $perPage,
    ): array {
        $query = $this->sessionBaseQuery($schoolId)
            ->where('sess.academic_year_id', $academicYearId);

        if ($sectionId !== null) {
            $query->where('sess.section_id', $sectionId);
        }
        if ($dateFrom !== null) {
            $query->where('sess.session_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('sess.session_date', '<=', $dateTo);
        }
        if ($status !== null) {
            $query->where('sess.status', $status);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('sess.session_date')
            ->orderByDesc('sess.id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($row): AttendanceSessionDTO => $this->mapSession($row))
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function getSectionAttendance(
        int $schoolId,
        int $sectionId,
        string $date,
        int $academicYearId,
    ): ?SectionAttendanceDTO {
        $resolved = $this->resolveSchoolIdForSection($sectionId);
        if ($resolved === null || $resolved !== $schoolId) {
            return null;
        }

        $sessions = $this->sessionBaseQuery($schoolId)
            ->where('sess.section_id', $sectionId)
            ->where('sess.session_date', $date)
            ->where('sess.academic_year_id', $academicYearId)
            ->orderBy('sess.id')
            ->get()
            ->map(fn ($row): AttendanceSessionDTO => $this->mapSession($row))
            ->all();

        $sessionIds = array_map(static fn (AttendanceSessionDTO $s): int => $s->id, $sessions);

        $records = [];
        if ($sessionIds !== []) {
            $records = DB::table($this->recordsTable())
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
                ->whereIn('session_id', $sessionIds)
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('attendance_date', $date)
                ->orderBy('student_id')
                ->get()
                ->map(fn ($r): AttendanceRecordDTO => $this->mapRecord($r))
                ->all();
        }

        return new SectionAttendanceDTO(
            schoolId: $schoolId,
            sectionId: $sectionId,
            date: $date,
            academicYearId: $academicYearId,
            sessions: $sessions,
            records: $records,
        );
    }

    public function getStudentAttendance(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage,
    ): array {
        $query = DB::table($this->recordsTable())
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
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId);

        if ($dateFrom !== null) {
            $query->where('attendance_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('attendance_date', '<=', $dateTo);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($r): AttendanceRecordDTO => $this->mapRecord($r))
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function getDailySectionSummary(
        int $schoolId,
        int $sectionId,
        ?string $date,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $query = DB::table($this->summaryTable())
            ->select([
                'section_id',
                'school_id',
                'academic_year_id',
                'attendance_date',
                'total_students',
                'present_count',
                'absent_count',
                'late_count',
            ])
            ->where('school_id', $schoolId)
            ->where('section_id', $sectionId);

        if ($date !== null) {
            $query->where('attendance_date', $date);
        }
        if ($dateFrom !== null) {
            $query->where('attendance_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('attendance_date', '<=', $dateTo);
        }

        return $query
            ->orderBy('attendance_date')
            ->get()
            ->map(static fn ($row): DailySectionSummaryDTO => new DailySectionSummaryDTO(
                sectionId: (int) $row->section_id,
                schoolId: (int) $row->school_id,
                academicYearId: (int) $row->academic_year_id,
                attendanceDate: (string) $row->attendance_date,
                totalStudents: (int) $row->total_students,
                presentCount: (int) $row->present_count,
                absentCount: (int) $row->absent_count,
                lateCount: (int) $row->late_count,
            ))
            ->all();
    }

    private function sessionBaseQuery(int $schoolId)
    {
        $sessions = $this->sessionsTable();

        return DB::table("{$sessions} as sess")
            ->where('sess.school_id', $schoolId)
            ->select([
                'sess.id',
                'sess.school_id',
                'sess.section_id',
                'sess.subject_id',
                'sess.academic_year_id',
                'sess.session_date',
                'sess.period_id',
                'sess.teacher_id',
                'sess.status',
            ]);
    }

    private function mapSession(object $row): AttendanceSessionDTO
    {
        return new AttendanceSessionDTO(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            sectionId: (int) $row->section_id,
            subjectId: (int) $row->subject_id,
            academicYearId: (int) $row->academic_year_id,
            sessionDate: (string) $row->session_date,
            periodId: $row->period_id !== null ? (int) $row->period_id : null,
            teacherId: (int) $row->teacher_id,
            status: (int) $row->status,
        );
    }

    private function mapRecord(object $row): AttendanceRecordDTO
    {
        return new AttendanceRecordDTO(
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

    private function resolveSchoolIdForSection(int $sectionId): ?int
    {
        $sections = SchemaHelper::qualified('enrollment', 'sections');
        $classes = SchemaHelper::qualified('enrollment', 'classes');

        $schoolId = DB::table("{$sections} as s")
            ->join("{$classes} as c", 'c.id', '=', 's.class_id')
            ->where('s.id', $sectionId)
            ->value('c.school_id');

        return $schoolId !== null ? (int) $schoolId : null;
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
