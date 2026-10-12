<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Business-rule failures of the sheet / excuse / staff workflows. Codes containing «not_found» map to 404 and
 * «conflict» to 409 on the API; Inertia pages flash the code and the UI localizes it (i18n errors.codes).
 */
final class AttendanceRuleViolation extends SisDomainException
{
    public static function invalidTransition(string $from, string $action): self
    {
        return new self("Sheet in status {$from} cannot «{$action}».", 'attendance.sheet_transition_invalid');
    }

    public static function versionConflict(int $sessionId, int $expected, int $actual): self
    {
        return new self(
            "Sheet {$sessionId} changed meanwhile (expected version {$expected}, found {$actual}).",
            'attendance.sheet_version_conflict',
        );
    }

    public static function notEditable(int $sessionId): self
    {
        return new self("Sheet {$sessionId} is not editable in its current status.", 'attendance.sheet_not_editable');
    }

    public static function incomplete(int $unmarked): self
    {
        return new self("{$unmarked} student(s) still have no status.", 'attendance.sheet_incomplete');
    }

    public static function emptyRoster(): self
    {
        return new self('The section has no enrolled students on this date.', 'attendance.sheet_roster_empty');
    }

    public static function studentNotInRoster(int $enrollmentId): self
    {
        return new self("Enrollment {$enrollmentId} is not on this lesson's roster.", 'attendance.student_not_in_roster');
    }

    public static function statusNotAllowed(int $status): self
    {
        return new self("Status {$status} is not allowed here.", 'attendance.status_not_allowed');
    }

    public static function reasonRequired(): self
    {
        return new self('A reason is required.', 'attendance.reason_required');
    }

    public static function lessonNotFound(): self
    {
        return new self('No such lesson in the effective timetable of that date.', 'attendance.lesson_not_found');
    }

    public static function lessonAccessDenied(): self
    {
        return new self('This lesson is not assigned to you.', 'attendance.lesson_access_denied');
    }

    public static function teacherProfileMissing(): self
    {
        return new self('Your account is not linked to a teacher of this school.', 'attendance.teacher_profile_missing');
    }

    public static function windowNotOpen(): self
    {
        return new self('Attendance for this lesson cannot be taken yet.', 'attendance.lesson_window_not_open');
    }

    public static function windowClosed(): self
    {
        return new self('The attendance window of this lesson is closed.', 'attendance.lesson_window_closed');
    }

    public static function holiday(string $date): self
    {
        return new self("{$date} is a holiday.", 'attendance.date_is_holiday');
    }

    public static function dateOutsideYear(): self
    {
        return new self('The date is outside the academic year.', 'attendance.date_outside_year');
    }

    public static function excuseNotPending(int $excuseId): self
    {
        return new self("Excuse {$excuseId} is no longer pending.", 'attendance.excuse_not_pending');
    }

    public static function excuseInvalid(string $reason): self
    {
        return new self($reason, 'attendance.excuse_invalid');
    }

    public static function excuseNotFound(int $excuseId): self
    {
        return new self("Excuse {$excuseId} not found.", 'attendance.excuse_not_found');
    }

    public static function checkinCodeInvalid(): self
    {
        return new self('The check-in code is invalid or expired.', 'attendance.checkin_code_invalid');
    }

    public static function evidenceDisabled(string $via): self
    {
        return new self("Check-in via {$via} is not enabled for this school.", 'attendance.evidence_disabled');
    }

    public static function exceptionalLessonInvalid(string $field): self
    {
        return new self("Exceptional lesson is invalid: {$field}.", 'attendance.exceptional_lesson_invalid');
    }

    public static function duplicateLesson(): self
    {
        return new self('An open sheet already exists for this section, subject and period.', 'attendance.duplicate_lesson_conflict');
    }

    public static function exportNotFound(): self
    {
        return new self('Export not found.', 'attendance.export_not_found');
    }

    public static function alreadyCheckedIn(): self
    {
        return new self('Your attendance for today is already recorded.', 'attendance.already_checked_in_conflict');
    }

    public static function staffRecordNotFound(): self
    {
        return new self('Staff attendance record not found.', 'attendance.staff_record_not_found');
    }

    public static function staffRecordConflict(): self
    {
        return new self('The staff record changed meanwhile.', 'attendance.staff_record_conflict');
    }

    public static function teacherNotInSchool(): self
    {
        return new self('The teacher does not belong to this school in this academic year.', 'attendance.teacher_not_in_school');
    }

    public static function alertNotFound(): self
    {
        return new self('Alert not found.', 'attendance.alert_not_found');
    }

    public static function reportTypeInvalid(string $type): self
    {
        return new self("Unknown report type {$type}.", 'attendance.report_type_invalid');
    }

    public static function policyInvalid(string $field): self
    {
        return new self("Invalid policy value: {$field}.", 'attendance.policy_invalid');
    }
}
