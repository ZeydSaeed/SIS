<?php

namespace App\Application\Exams\Contracts;

/** «كشف المرشحين» — every seat of one exam session (all statuses) with the student and placement, for the roster page. */
interface ExamSessionRosterReadRepositoryInterface
{
    /**
     * @return array{id: int, exam_id: int, exam_name: string, academic_year_id: int, subject_name: string,
     *     session_date: string, start_time: string, end_time: string, status: int}|null
     */
    public function session(int $schoolId, int $examSessionId): ?array;

    /**
     * @return list<array{exam_enrollment_id: int, seat_number: ?string, status: int, student_code: string, student_name: string,
     *     class_name: ?string, section_name: ?string, has_current_grade: bool}>
     */
    public function seats(int $schoolId, int $examSessionId): array;
}
