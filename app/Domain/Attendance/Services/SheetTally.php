<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Data\RosterEntry;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;

/**
 * Counts of a sheet against its roster: a roster student without a stored decided status is «غير محدد».
 */
final class SheetTally
{
    /**
     * @param  list<RosterEntry>  $roster
     * @param  array<int, int>  $statusByEnrollment  enrollment_id → stored status
     * @return array{expected: int, present: int, absent: int, late: int, excused: int, left_early: int, unmarked: int}
     */
    public static function of(array $roster, array $statusByEnrollment): array
    {
        $counts = ['expected' => count($roster), 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'left_early' => 0, 'unmarked' => 0];
        foreach ($roster as $entry) {
            $key = match (AttendanceRecordStatus::tryFrom($statusByEnrollment[$entry->enrollmentId] ?? 0)) {
                AttendanceRecordStatus::Present => 'present',
                AttendanceRecordStatus::Absent => 'absent',
                AttendanceRecordStatus::Late => 'late',
                AttendanceRecordStatus::Excused => 'excused',
                AttendanceRecordStatus::LeftEarly => 'left_early',
                default => 'unmarked',
            };
            $counts[$key]++;
        }

        return $counts;
    }
}
