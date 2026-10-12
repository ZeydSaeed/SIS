<?php

namespace App\Application\Attendance\Contracts;

/**
 * Read models of the attendance workspace (teacher day, sheet, review board, excuses, staff, alerts). Every
 * method is school-scoped; lists are server-paginated; no method returns more than its documented cap.
 */
interface AttendanceWorkspaceReadInterface
{
    /**
     * Live sessions of those lessons on the date, with their counts.
     *
     * @param  list<int>  $scheduleIds
     * @return array<int, array<string, mixed>> schedule_id → session summary
     */
    public function sessionsForLessons(int $schoolId, string $date, array $scheduleIds): array;

    /**
     * @return array<string, mixed>|null
     */
    public function sheetHeader(int $schoolId, int $sessionId): ?array;

    /**
     * Roster rows joined with the stored marks (names, codes, status, minutes late, note, last change).
     *
     * @param  list<int>  $enrollmentIds
     * @return list<array<string, mixed>>
     */
    public function sheetRows(int $schoolId, int $sessionId, int $academicYearId, array $enrollmentIds): array;

    /**
     * Rows stored on the sheet for students who are no longer on its roster (kept, never deleted).
     *
     * @param  list<int>  $enrollmentIds
     * @return list<array<string, mixed>>
     */
    public function offRosterRows(int $schoolId, int $sessionId, int $academicYearId, array $enrollmentIds): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function sheetEvents(int $schoolId, int $sessionId): array;

    /**
     * @param  array<string, mixed>  $filters  date_from, date_to, sheet_status[], teacher_id, subject_id, section_id, class_id, lesson_type, late
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, totals: array<int, int>}
     */
    public function reviewBoard(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array;

    /**
     * The teacher's recent sheets that need attention (returned with notes) and their latest decisions.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherNotices(int $schoolId, int $academicYearId, int $teacherId, int $limit): array;

    /**
     * @param  array<string, mixed>  $filters  status, q (student name / code), date_from, date_to
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function excuses(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array;

    /**
     * School teachers of the year with their work-attendance row of the date (if any).
     *
     * @return list<array<string, mixed>>
     */
    public function staffDay(int $schoolId, int $academicYearId, string $date): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function staffHistory(int $schoolId, int $teacherId, string $from, string $to): array;

    /**
     * @param  array<string, mixed>  $filters  status, rule_code, severity
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, open: int}
     */
    public function alerts(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array;

    /**
     * The user's most recent queued report files.
     *
     * @return list<array<string, mixed>>
     */
    public function exports(int $schoolId, int $userId, int $limit): array;

    /**
     * Filter options of the school year: periods, classes with sections, subjects, teachers.
     *
     * @return array{periods: list<array<string, mixed>>, classes: list<array<string, mixed>>, subjects: list<array<string, mixed>>, teachers: list<array<string, mixed>>}
     */
    public function lookups(int $schoolId, int $academicYearId): array;

    /**
     * Students of the school year matching a name / code (excuse entry), capped.
     *
     * @return list<array<string, mixed>>
     */
    public function searchEnrollments(int $schoolId, int $academicYearId, string $term, int $limit): array;
}
