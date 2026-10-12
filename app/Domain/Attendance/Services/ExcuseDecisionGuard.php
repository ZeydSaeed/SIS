<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Data\ExcuseSnapshot;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\ValueObjects\ExcuseStatus;

/** An excuse is decided once, in its own school and year, at the version the reviewer saw; a rejection explains itself. */
final class ExcuseDecisionGuard
{
    public static function note(bool $approve, ?string $note): ?string
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        if (! $approve && $note === null) {
            throw AttendanceRuleViolation::reasonRequired();
        }

        return $note;
    }

    public static function assertDecidable(?ExcuseSnapshot $excuse, int $excuseId, int $schoolId, int $academicYearId, int $expectedVersion): ExcuseSnapshot
    {
        if ($excuse === null || $excuse->schoolId !== $schoolId || $excuse->academicYearId !== $academicYearId) {
            throw AttendanceRuleViolation::excuseNotFound($excuseId);
        }
        if ($excuse->status !== ExcuseStatus::Pending->value || $excuse->version !== $expectedVersion) {
            throw AttendanceRuleViolation::excuseNotPending($excuseId);
        }

        return $excuse;
    }
}
