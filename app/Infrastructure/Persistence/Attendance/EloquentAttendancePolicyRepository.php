<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Domain\Attendance\Repositories\AttendancePolicyRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use Illuminate\Support\Facades\DB;

final class EloquentAttendancePolicyRepository implements AttendancePolicyRepositoryInterface
{
    private const COLUMNS = [
        'id', 'version_no', 'timezone', 'open_before_minutes', 'submit_deadline_minutes', 'edit_window_days', 'late_grace_minutes',
        'late_as_absent_minutes', 'require_complete', 'default_mark', 'teacher_can_mark_excused', 'absence_alert_count',
        'absence_alert_window_days', 'absence_rate_alert_percent', 'late_alert_count', 'returned_alert_count', 'staff_day_start',
        'staff_late_grace_minutes', 'checkin_code_ttl_minutes', 'qr_enabled', 'nfc_enabled',
    ];

    public function current(int $schoolId, int $academicYearId): ?AttendancePolicy
    {
        $row = DB::table('attendance.policies')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('effective_to')
            ->first(self::COLUMNS);

        return $row !== null ? $this->map($row) : null;
    }

    public function find(int $policyId): ?AttendancePolicy
    {
        $row = DB::table('attendance.policies')->where('id', $policyId)->first(self::COLUMNS);

        return $row !== null ? $this->map($row) : null;
    }

    public function saveNewVersion(int $schoolId, int $academicYearId, AttendancePolicy $values, ?int $createdBy): int
    {
        $current = DB::table('attendance.policies')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('effective_to')
            ->lockForUpdate()
            ->first(['id', 'version_no']);
        $nextVersion = (int) DB::table('attendance.policies')->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->max('version_no') + 1;

        if ($current !== null) {
            DB::table('attendance.policies')->where('id', $current->id)->update(['effective_to' => now()]);
        }

        $data = $values->toArray();
        unset($data['id'], $data['version_no']);

        return (int) DB::table('attendance.policies')->insertGetId($data + [
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'version_no' => $nextVersion,
            'effective_from' => now(),
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
    }

    private function map(object $row): AttendancePolicy
    {
        return new AttendancePolicy(
            id: (int) $row->id,
            timezone: (string) $row->timezone,
            openBeforeMinutes: (int) $row->open_before_minutes,
            submitDeadlineMinutes: (int) $row->submit_deadline_minutes,
            editWindowDays: (int) $row->edit_window_days,
            lateGraceMinutes: (int) $row->late_grace_minutes,
            lateAsAbsentMinutes: $row->late_as_absent_minutes !== null ? (int) $row->late_as_absent_minutes : null,
            requireComplete: (bool) $row->require_complete,
            defaultMark: (int) $row->default_mark,
            teacherCanMarkExcused: (bool) $row->teacher_can_mark_excused,
            absenceAlertCount: (int) $row->absence_alert_count,
            absenceAlertWindowDays: (int) $row->absence_alert_window_days,
            absenceRateAlertPercent: (int) $row->absence_rate_alert_percent,
            lateAlertCount: (int) $row->late_alert_count,
            returnedAlertCount: (int) $row->returned_alert_count,
            staffDayStart: substr((string) $row->staff_day_start, 0, 5),
            staffLateGraceMinutes: (int) $row->staff_late_grace_minutes,
            checkinCodeTtlMinutes: (int) $row->checkin_code_ttl_minutes,
            qrEnabled: (bool) $row->qr_enabled,
            nfcEnabled: (bool) $row->nfc_enabled,
            versionNo: (int) $row->version_no,
        );
    }
}
