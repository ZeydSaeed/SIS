<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Exceptions\AttendancePayloadTooLargeException;
use App\Domain\Attendance\Exceptions\DuplicateStudentInPayloadException;
use App\Domain\Attendance\Exceptions\EmptyAttendancePayloadException;
use App\Domain\Attendance\Exceptions\InvalidAttendanceStatusException;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;

/**
 * Pure payload shape guards for MarkSectionAttendance (ATT-D6).
 */
final class AttendanceMarkPayloadGuard
{
    public const MAX_RECORDS = 500;

    /**
     * @param  list<array{studentId: int, enrollmentId: int, status: int, notes?: string|null}>  $records
     */
    public static function assertValid(array $records): void
    {
        if ($records === []) {
            throw EmptyAttendancePayloadException::create();
        }

        if (count($records) > self::MAX_RECORDS) {
            throw AttendancePayloadTooLargeException::forLimit(self::MAX_RECORDS);
        }

        $seen = [];
        foreach ($records as $row) {
            $studentId = (int) $row['studentId'];
            if (isset($seen[$studentId])) {
                throw DuplicateStudentInPayloadException::create();
            }
            $seen[$studentId] = true;

            $status = (int) $row['status'];
            if (! AttendanceRecordStatus::isValid($status)) {
                throw InvalidAttendanceStatusException::forValue($status);
            }
        }
    }
}
