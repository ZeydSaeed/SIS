<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Domain\Attendance\Data\ExcuseSnapshot;
use App\Domain\Attendance\Repositories\AttendanceExcuseRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\EvidenceMethod;
use App\Domain\Attendance\ValueObjects\ExcuseStatus;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceExcuseRepository implements AttendanceExcuseRepositoryInterface
{
    public function create(int $schoolId, int $academicYearId, int $studentId, int $enrollmentId, string $dateFrom, string $dateTo, ?int $sessionId, int $excuseType, string $reason, ?int $documentFileId, ?int $submittedBy): int
    {
        return (int) DB::table('attendance.excuses')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'session_id' => $sessionId,
            'excuse_type' => $excuseType,
            'reason' => $reason,
            'document_file_id' => $documentFileId,
            'status' => ExcuseStatus::Pending->value,
            'submitted_by' => $submittedBy,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function lock(int $excuseId): ?ExcuseSnapshot
    {
        $row = DB::table('attendance.excuses')->where('id', $excuseId)->lockForUpdate()->first([
            'id', 'school_id', 'academic_year_id', 'student_id', 'enrollment_id', 'date_from', 'date_to', 'session_id', 'excuse_type', 'status', 'version', 'submitted_by',
        ]);
        if ($row === null) {
            return null;
        }

        return new ExcuseSnapshot(
            (int) $row->id, (int) $row->school_id, (int) $row->academic_year_id, (int) $row->student_id, (int) $row->enrollment_id,
            (string) $row->date_from, (string) $row->date_to, $row->session_id !== null ? (int) $row->session_id : null,
            (int) $row->excuse_type, (int) $row->status, (int) $row->version, $row->submitted_by !== null ? (int) $row->submitted_by : null,
        );
    }

    public function decide(int $excuseId, int $expectedVersion, int $status, ?int $reviewedBy, ?string $note, int $appliedRecords): bool
    {
        return DB::table('attendance.excuses')
            ->where('id', $excuseId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $status,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'decision_note' => $note,
                'applied_records' => $appliedRecords,
                'version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]) === 1;
    }

    public function applyToAbsences(ExcuseSnapshot $excuse, ?int $appliedBy): array
    {
        $rows = DB::select('
            WITH target AS (
                SELECT r.id, r.status AS previous_status
                FROM attendance.records r
                JOIN attendance.sessions s ON s.id = r.session_id
                WHERE r.school_id = ? AND r.academic_year_id = ? AND r.student_id = ?
                  AND r.attendance_date BETWEEN ?::date AND ?::date
                  AND r.status = ?
                  AND s.status <> ?
                  AND (?::bigint IS NULL OR r.session_id = ?::bigint)
                FOR UPDATE OF r
            )
            UPDATE attendance.records r
            SET status = ?, excuse_id = ?, marked_via = ?, recorded_by = ?, updated_at = NOW()
            FROM target t, attendance.sessions s
            WHERE r.id = t.id AND r.academic_year_id = ? AND s.id = r.session_id
            RETURNING r.id AS record_id, r.session_id, s.section_id, r.attendance_date::text AS date, t.previous_status, s.sheet_status
        ', [
            $excuse->schoolId, $excuse->academicYearId, $excuse->studentId, $excuse->dateFrom, $excuse->dateTo,
            AttendanceRecordStatus::Absent->value, SessionStatus::Cancelled->value, $excuse->sessionId, $excuse->sessionId,
            AttendanceRecordStatus::Excused->value, $excuse->id, EvidenceMethod::ExcuseApplication->value, $appliedBy, $excuse->academicYearId,
        ]);

        return array_map(static fn (object $r): array => [
            'record_id' => (int) $r->record_id,
            'session_id' => (int) $r->session_id,
            'section_id' => (int) $r->section_id,
            'date' => (string) $r->date,
            'previous_status' => (int) $r->previous_status,
            'sheet_status' => (int) $r->sheet_status,
        ], $rows);
    }

    public function sessionMatches(int $sessionId, int $schoolId, int $academicYearId, int $sectionId): bool
    {
        return DB::table('attendance.sessions')
            ->where('id', $sessionId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('section_id', $sectionId)
            ->exists();
    }

    public function documentBelongsToSchool(int $fileId, int $schoolId): bool
    {
        return DB::table('documents.files')->where('id', $fileId)->where('school_id', $schoolId)->exists();
    }
}
