<?php

namespace App\Application\Attendance\Contracts;

/**
 * Aggregated attendance reports. Counted sessions: not cancelled, not rejected (or approved only when the
 * filter asks); unmarked draft rows never count. Rates are computed by the Domain statistics service.
 */
interface AttendanceReportReadInterface
{
    public const TYPES = ['students', 'student_detail', 'sections', 'subjects', 'teachers', 'daily', 'staff', 'lesson_types', 'periods', 'excuses', 'risk'];

    /**
     * Risk-indicator inputs for students with at least one absence in the last 60 days up to $today.
     *
     * @param  array<string, mixed>  $filters  class_id / section_id (single or lists)
     * @return list<array{student_id: int, full_name: string, student_code: string, recent_absences: int, recent_decided: int, previous_absences: int, previous_decided: int, absence_dates: list<string>, subjects_over: int, practical_absences: int}>
     */
    public function riskInputs(int $schoolId, int $academicYearId, string $today, int $subjectThreshold, array $filters): array;

    /**
     * @param  array<string, mixed>  $filters  date_from, date_to, class_id, section_id, subject_id, teacher_id, student_id, status, lesson_type, approved_only
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function report(int $schoolId, int $academicYearId, string $type, array $filters, int $limit, int $offset): array;

    /**
     * Totals of the school on a date range (status counts + sheet workflow counts).
     *
     * @return array<string, int>
     */
    public function schoolTotals(int $schoolId, int $academicYearId, string $from, string $to): array;

    /**
     * Students with the most absences in the range (capped).
     *
     * @return list<array<string, mixed>>
     */
    public function topAbsentees(int $schoolId, int $academicYearId, string $from, string $to, int $limit): array;
}
