<?php

namespace App\Domain\Exams\Repositories;

use App\Domain\Exams\Data\CreateExamData;
use App\Domain\Exams\Data\ExamSnapshot;

interface ExamRepositoryInterface
{
    public function insert(CreateExamData $data): int;

    public function findByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot;

    public function lockByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot;

    /**
     * @param  array{
     *   name?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   exam_type_id?: int,
     *   term_id?: int,
     *   status?: int
     * }  $fields
     */
    public function updateAllowlisted(int $examId, int $schoolId, array $fields): void;

    public function academicYearExists(int $academicYearId): bool;

    public function examTypeExists(int $examTypeId): bool;

    public function termBelongsToAcademicYear(int $termId, int $academicYearId): bool;

    public function hasCurrentGradeForExam(int $examId, int $schoolId): bool;

    public function hasCompletedSessionForExam(int $examId, int $schoolId): bool;

    /**
     * @return array{total: int, scheduled: int, in_progress: int, completed: int, cancelled: int}
     */
    public function sessionStatusCounts(int $examId, int $schoolId): array;

    /**
     * Cancel Scheduled/InProgress sessions for the exam.
     *
     * @return list<array{id: int, previous_status: int}>
     */
    public function cancelOpenSessionsForExam(int $examId, int $schoolId): array;

    /**
     * Withdraw active seats (Registered/Confirmed/Present) under the exam.
     *
     * @return list<array{id: int, exam_session_id: int, previous_status: int}>
     */
    public function withdrawActiveEnrollmentsForExam(int $examId, int $schoolId): array;
}
