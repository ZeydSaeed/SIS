<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Data\RosterEntry;
use App\Domain\Attendance\Data\SheetMark;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Exceptions\DuplicateStudentInPayloadException;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;

/**
 * Validates and normalizes the marks a sheet save carries:
 *  - every mark belongs to the lesson roster (never a student of another section / group / date);
 *  - no student twice;
 *  - «غياب بعذر» only for reviewers, or teachers when the policy allows it;
 *  - late arrivals are classified by policy (grace → present, beyond the absent threshold → absent).
 */
final class SheetMarksGuard
{
    /**
     * @param  list<SheetMark>  $marks
     * @param  list<RosterEntry>  $roster
     * @return array{marks: list<array{mark: SheetMark, student_id: int}>, normalized: int}
     */
    public static function normalize(array $marks, array $roster, AttendancePolicy $policy, bool $reviewer): array
    {
        $studentByEnrollment = [];
        foreach ($roster as $entry) {
            $studentByEnrollment[$entry->enrollmentId] = $entry->studentId;
        }

        $seen = [];
        $out = [];
        $normalized = 0;
        foreach ($marks as $mark) {
            if (! isset($studentByEnrollment[$mark->enrollmentId])) {
                throw AttendanceRuleViolation::studentNotInRoster($mark->enrollmentId);
            }
            if (isset($seen[$mark->enrollmentId])) {
                throw DuplicateStudentInPayloadException::create();
            }
            $seen[$mark->enrollmentId] = true;

            $final = self::finalStatus($mark, $policy, $reviewer);
            if ($final->value !== $mark->status) {
                $normalized++;
            }
            $out[] = [
                'mark' => new SheetMark($mark->enrollmentId, $final->value, $final === AttendanceRecordStatus::Late ? $mark->minutesLate : null, $mark->note),
                'student_id' => $studentByEnrollment[$mark->enrollmentId],
            ];
        }

        return ['marks' => $out, 'normalized' => $normalized];
    }

    private static function finalStatus(SheetMark $mark, AttendancePolicy $policy, bool $reviewer): AttendanceRecordStatus
    {
        $status = AttendanceRecordStatus::tryFrom($mark->status);
        if ($status === null) {
            throw AttendanceRuleViolation::statusNotAllowed($mark->status);
        }
        if ($status === AttendanceRecordStatus::Excused && ! $reviewer && ! $policy->teacherCanMarkExcused) {
            throw AttendanceRuleViolation::statusNotAllowed($mark->status);
        }

        return $status === AttendanceRecordStatus::Late ? $policy->classifyLate($mark->minutesLate) : $status;
    }
}
