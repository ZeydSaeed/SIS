<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Domain\Attendance\Data\StaffRecordSnapshot;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentStaffAttendanceRepository implements StaffAttendanceRepositoryInterface
{
    public function lockForDay(int $schoolId, int $teacherId, string $workDate): ?StaffRecordSnapshot
    {
        $row = DB::table('attendance.staff_records')
            ->where('school_id', $schoolId)
            ->where('teacher_id', $teacherId)
            ->where('work_date', $workDate)
            ->lockForUpdate()
            ->first(['id', 'school_id', 'academic_year_id', 'teacher_id', 'work_date', 'status', 'check_in_at', 'check_out_at', 'minutes_late', 'method', 'notes', 'version']);
        if ($row === null) {
            return null;
        }

        return new StaffRecordSnapshot(
            (int) $row->id, (int) $row->school_id, (int) $row->academic_year_id, (int) $row->teacher_id, (string) $row->work_date, (int) $row->status,
            $row->check_in_at !== null ? (string) $row->check_in_at : null, $row->check_out_at !== null ? (string) $row->check_out_at : null,
            $row->minutes_late !== null ? (int) $row->minutes_late : null, (int) $row->method, $row->notes !== null ? (string) $row->notes : null, (int) $row->version,
        );
    }

    public function insert(int $schoolId, int $academicYearId, int $teacherId, string $workDate, array $values, ?int $recordedBy): int
    {
        return (int) DB::table('attendance.staff_records')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'teacher_id' => $teacherId,
            'work_date' => $workDate,
            'status' => $values['status'],
            'check_in_at' => $values['check_in_at'],
            'check_out_at' => $values['check_out_at'],
            'minutes_late' => $values['minutes_late'],
            'method' => $values['method'],
            'notes' => $values['notes'],
            'recorded_by' => $recordedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function update(int $recordId, int $expectedVersion, array $values, ?int $recordedBy): bool
    {
        return DB::table('attendance.staff_records')
            ->where('id', $recordId)
            ->where('version', $expectedVersion)
            ->update([
                'status' => $values['status'],
                'check_in_at' => $values['check_in_at'],
                'check_out_at' => $values['check_out_at'],
                'minutes_late' => $values['minutes_late'],
                'method' => $values['method'],
                'notes' => $values['notes'],
                'recorded_by' => $recordedBy,
                'version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]) === 1;
    }

    public function teacherBelongsToSchool(int $teacherId, int $schoolId, int $academicYearId): bool
    {
        return DB::table('teachers.teacher_schools')
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('left_at')
            ->exists();
    }

    public function validCodeHashes(int $schoolId, \DateTimeImmutable $now, int $kind = 1): array
    {
        return DB::table('attendance.checkin_codes')
            ->where('school_id', $schoolId)
            ->where('kind', $kind)
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', $now->format(DATE_ATOM))
            ->where('valid_until', '>=', $now->format(DATE_ATOM))
            ->pluck('code_hash')
            ->map(static fn ($hash): string => (string) $hash)
            ->all();
    }

    public function storeCode(int $schoolId, string $codeHash, \DateTimeImmutable $validFrom, \DateTimeImmutable $validUntil, ?int $createdBy, int $kind = 1, ?string $label = null): int
    {
        return (int) DB::table('attendance.checkin_codes')->insertGetId([
            'school_id' => $schoolId,
            'code_hash' => $codeHash,
            'valid_from' => $validFrom->format(DATE_ATOM),
            'valid_until' => $validUntil->format(DATE_ATOM),
            'created_by' => $createdBy,
            'kind' => $kind,
            'label' => $label,
            'created_at' => now(),
        ]);
    }

    public function revokeCodes(int $schoolId, \DateTimeImmutable $now, int $kind = 1): void
    {
        DB::table('attendance.checkin_codes')
            ->where('school_id', $schoolId)
            ->where('kind', $kind)
            ->whereNull('revoked_at')
            ->where('valid_until', '>=', $now->format(DATE_ATOM))
            ->update(['revoked_at' => $now->format(DATE_ATOM)]);
    }
}
