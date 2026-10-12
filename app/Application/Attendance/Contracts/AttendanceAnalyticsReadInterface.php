<?php

namespace App\Application\Attendance\Contracts;

/**
 * Aggregates feeding the alert rules. Only sheets that count are used: not cancelled, not rejected, and with a
 * decided status (unmarked draft rows never count).
 */
interface AttendanceAnalyticsReadInterface
{
    /**
     * @return list<array{student_id: int, absences: int, decided: int, lates: int, practical_absences: int}>
     */
    public function studentWindowStats(int $schoolId, int $academicYearId, string $from, string $to): array;

    /**
     * @return list<array{student_id: int, subject_id: int, absences: int}>
     */
    public function studentSubjectAbsences(int $schoolId, int $academicYearId, string $from, string $to): array;

    /**
     * Students counted as attending two or more sessions of the same date and period.
     *
     * @return list<array{student_id: int, session_ids: list<int>, date: string}>
     */
    public function attendanceConflicts(int $schoolId, int $academicYearId, string $date): array;

    /**
     * @return list<array{section_id: int, absent: int, total: int}>
     */
    public function sectionDay(int $schoolId, int $academicYearId, string $date): array;

    /**
     * @return array<int, int> schedule_id → sheet_status of the live session of that date
     */
    public function sheetStatusesOn(int $schoolId, string $date): array;

    /**
     * @return array<int, int> teacher_id → sheets returned for correction in the period
     */
    public function returnedCounts(int $schoolId, int $academicYearId, string $from, string $to): array;

    /**
     * @return list<int> teachers recorded unavailable (absent / leave / mission) that day
     */
    public function unavailableTeachers(int $schoolId, string $date): array;
}
