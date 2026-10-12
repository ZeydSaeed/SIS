<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Data\AlertCandidate;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;

/**
 * Deterministic, explainable alert rules over aggregated attendance data. Each rule compares one measured
 * value with one configurable threshold (attendance.policies) and produces a reviewable alert. No rule labels a
 * person; no rule changes any record.
 */
final class AttendanceAlertRules
{
    public const STUDENT_ABSENCE_COUNT = 'student_absence_count';

    public const STUDENT_ABSENCE_RATE = 'student_absence_rate';

    public const STUDENT_SUBJECT_ABSENCE = 'student_subject_absence';

    public const STUDENT_PRACTICAL_ABSENCE = 'student_practical_absence';

    public const STUDENT_LATE_REPEAT = 'student_late_repeat';

    public const STUDENT_SESSION_CONFLICT = 'student_session_conflict';

    public const SECTION_ABSENCE_SPIKE = 'section_absence_spike';

    public const TEACHER_SHEET_MISSING = 'teacher_sheet_missing';

    public const TEACHER_SHEET_RETURNED = 'teacher_sheet_returned_repeat';

    public const TEACHER_ABSENT_WITH_LESSONS = 'teacher_absent_with_lessons';

    public const ALL = [
        self::STUDENT_ABSENCE_COUNT, self::STUDENT_ABSENCE_RATE, self::STUDENT_SUBJECT_ABSENCE, self::STUDENT_PRACTICAL_ABSENCE,
        self::STUDENT_LATE_REPEAT, self::STUDENT_SESSION_CONFLICT, self::SECTION_ABSENCE_SPIKE, self::TEACHER_SHEET_MISSING,
        self::TEACHER_SHEET_RETURNED, self::TEACHER_ABSENT_WITH_LESSONS,
    ];

    /**
     * @param  list<array{student_id: int, absences: int, decided: int, lates: int, practical_absences: int}>  $students
     * @return list<AlertCandidate>
     */
    public static function forStudents(array $students, AttendancePolicy $policy): array
    {
        $out = [];
        foreach ($students as $row) {
            $id = (int) $row['student_id'];
            $absences = (int) $row['absences'];
            if ($absences >= $policy->absenceAlertCount) {
                $out[] = new AlertCandidate(self::STUDENT_ABSENCE_COUNT, $absences >= 2 * $policy->absenceAlertCount ? 3 : 2, 'student', $id, $absences, $policy->absenceAlertCount);
            }
            $rate = AttendanceStatistics::percent($absences, (int) $row['decided']);
            if ($rate !== null && (int) $row['decided'] >= 5 && $rate >= $policy->absenceRateAlertPercent) {
                $out[] = new AlertCandidate(self::STUDENT_ABSENCE_RATE, 2, 'student', $id, $rate, $policy->absenceRateAlertPercent, ['decided' => (int) $row['decided']]);
            }
            if ((int) $row['lates'] >= $policy->lateAlertCount) {
                $out[] = new AlertCandidate(self::STUDENT_LATE_REPEAT, 1, 'student', $id, (int) $row['lates'], $policy->lateAlertCount);
            }
            if ((int) $row['practical_absences'] >= $policy->absenceAlertCount) {
                $out[] = new AlertCandidate(self::STUDENT_PRACTICAL_ABSENCE, 2, 'student', $id, (int) $row['practical_absences'], $policy->absenceAlertCount);
            }
        }

        return $out;
    }

    /**
     * Absences of one student in one subject.
     *
     * @param  list<array{student_id: int, subject_id: int, absences: int}>  $rows
     * @return list<AlertCandidate>
     */
    public static function forStudentSubjects(array $rows, AttendancePolicy $policy): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ((int) $row['absences'] >= $policy->absenceAlertCount) {
                $out[] = new AlertCandidate(self::STUDENT_SUBJECT_ABSENCE, 1, 'student', (int) $row['student_id'], (int) $row['absences'], $policy->absenceAlertCount, ['subject_id' => (int) $row['subject_id']]);
            }
        }

        return $out;
    }

    /**
     * A student counted as attending two sessions of the same date and period.
     *
     * @param  list<array{student_id: int, session_ids: list<int>, date: string}>  $rows
     * @return list<AlertCandidate>
     */
    public static function forConflicts(array $rows): array
    {
        return array_map(
            static fn (array $row): AlertCandidate => new AlertCandidate(self::STUDENT_SESSION_CONFLICT, 3, 'student', (int) $row['student_id'], count($row['session_ids']), 1, ['session_ids' => $row['session_ids'], 'date' => $row['date']]),
            $rows,
        );
    }

    /**
     * A section whose absence rate on a day is at least twice the school's rate and above the policy rate.
     *
     * @param  list<array{section_id: int, absent: int, total: int}>  $sections
     * @return list<AlertCandidate>
     */
    public static function forSectionSpikes(array $sections, AttendancePolicy $policy): array
    {
        $absent = array_sum(array_column($sections, 'absent'));
        $total = array_sum(array_column($sections, 'total'));
        $schoolRate = AttendanceStatistics::percent((int) $absent, (int) $total) ?? 0.0;
        $threshold = max((float) $policy->absenceRateAlertPercent, 2 * $schoolRate);

        $out = [];
        foreach ($sections as $row) {
            $rate = AttendanceStatistics::percent((int) $row['absent'], (int) $row['total']);
            if ($rate !== null && (int) $row['total'] >= 5 && $rate >= $threshold) {
                $out[] = new AlertCandidate(self::SECTION_ABSENCE_SPIKE, 2, 'section', (int) $row['section_id'], $rate, round($threshold, 1), ['school_rate' => $schoolRate]);
            }
        }

        return $out;
    }

    /**
     * @param  list<array{teacher_id: int, missing: int, returned: int, absent_lessons: int}>  $teachers
     * @return list<AlertCandidate>
     */
    public static function forTeachers(array $teachers, AttendancePolicy $policy): array
    {
        $out = [];
        foreach ($teachers as $row) {
            $id = (int) $row['teacher_id'];
            if ((int) $row['missing'] > 0) {
                $out[] = new AlertCandidate(self::TEACHER_SHEET_MISSING, (int) $row['missing'] >= 3 ? 2 : 1, 'teacher', $id, (int) $row['missing'], 1);
            }
            if ((int) $row['returned'] >= $policy->returnedAlertCount) {
                $out[] = new AlertCandidate(self::TEACHER_SHEET_RETURNED, 1, 'teacher', $id, (int) $row['returned'], $policy->returnedAlertCount);
            }
            if ((int) $row['absent_lessons'] > 0) {
                $out[] = new AlertCandidate(self::TEACHER_ABSENT_WITH_LESSONS, 3, 'teacher', $id, (int) $row['absent_lessons'], 1);
            }
        }

        return $out;
    }
}
