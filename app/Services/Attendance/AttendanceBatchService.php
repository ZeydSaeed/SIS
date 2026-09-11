<?php

/** @architecture-legacy-allowed migrate to Application/Attendance — R1.9 Option B QUARANTINED */

namespace App\Services\Attendance;

use App\Domain\Attendance\Exceptions\LegacyAttendanceWriterQuarantinedException;

/**
 * DEPRECATED / QUARANTINED (R1.9 Option B).
 *
 * Authoritative Attendance writes: Application/Attendance CQRS
 * (CreateAttendanceSession, MarkSectionAttendance, CorrectAttendanceRecord, CloseAttendanceSession).
 *
 * This class is preserved temporarily. Public write methods fail closed.
 * Full deletion requires a separate human authorization (not R1.9 Option B).
 */
class AttendanceBatchService
{
    /**
     * @param  list<array{student_id: int, enrollment_id: int, status: int, notes?: string|null}>  $records
     *
     * @throws LegacyAttendanceWriterQuarantinedException
     */
    public function recordSectionAttendance(
        int $sessionId,
        int $sectionId,
        int $academicYearId,
        int $schoolId,
        array $records,
        int $recordedBy,
    ): int {
        throw LegacyAttendanceWriterQuarantinedException::forMethod('recordSectionAttendance');
    }

    /**
     * @throws LegacyAttendanceWriterQuarantinedException
     */
    public function refreshDailySummary(
        int $sectionId,
        int $schoolId,
        int $academicYearId,
        string $date,
    ): void {
        throw LegacyAttendanceWriterQuarantinedException::forMethod('refreshDailySummary');
    }
}
