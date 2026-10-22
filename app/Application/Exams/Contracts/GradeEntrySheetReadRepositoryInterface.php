<?php

namespace App\Application\Exams\Contracts;

/**
 * Read model of the exam-session grade-entry sheet. Scope filtering (which sessions / seats the user may grade) is
 * applied by the caller through the id lists — a null list means "all of the school".
 */
interface GradeEntrySheetReadRepositoryInterface
{
    /**
     * Non-cancelled sessions of non-cancelled exams of the year.
     *
     * @param  list<int>|null  $onlySessionIds
     * @return list<array{id: int, exam_name: string, subject_name: string, session_date: string, status: int, max_grade: int}>
     */
    public function sessions(int $schoolId, int $academicYearId, ?array $onlySessionIds): array;

    /**
     * @return array{id: int, academic_year_id: int, academic_year_name: string, exam_name: string, exam_status: int, term_name: string,
     *     subject_name: string, session_date: string, start_time: string, end_time: string, status: int, max_grade: int, pass_grade: int}|null
     */
    public function session(int $schoolId, int $examSessionId): ?array;

    /**
     * Active seats (registered / confirmed / present) of the session with the student, placement and current grade.
     *
     * @param  list<int>|null  $onlySeatIds
     * @return list<array{exam_enrollment_id: int, seat_number: ?string, student_code: string, student_name: string,
     *     class_name: string, section_name: string, grade_id: ?int, score: ?string, is_absent: bool, grade_status: ?int}>
     */
    public function seats(int $schoolId, int $examSessionId, ?array $onlySeatIds): array;
}
