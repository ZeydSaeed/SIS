<?php

namespace App\Domain\Exams\Repositories;

use App\Domain\Exams\Data\CreateExamData;
use App\Domain\Exams\Data\CreateExamEnrollmentData;
use App\Domain\Exams\Data\CreateExamSessionData;
use App\Domain\Exams\Data\ExamEnrollmentSnapshot;
use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Data\ExamSnapshot;

interface ExamRepositoryInterface
{
    public function insert(CreateExamData $data): int;

    public function insertSession(CreateExamSessionData $data): int;

    public function insertExamEnrollment(CreateExamEnrollmentData $data): int;

    public function findExamEnrollmentByIdAndSchool(int $examEnrollmentId, int $schoolId): ?ExamEnrollmentSnapshot;

    public function lockExamEnrollmentByIdAndSchool(int $examEnrollmentId, int $schoolId): ?ExamEnrollmentSnapshot;

    /**
     * @param  array{status?: int, seat_number?: string|null}  $fields
     */
    public function updateExamEnrollmentAllowlisted(int $examEnrollmentId, int $schoolId, array $fields): void;

    public function findByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot;

    public function lockByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot;

    public function findSessionByIdAndSchool(int $examSessionId, int $schoolId): ?ExamSessionSnapshot;

    public function lockSessionByIdAndSchool(int $examSessionId, int $schoolId): ?ExamSessionSnapshot;

    public function examEnrollmentSeatExists(int $examSessionId, int $enrollmentId, int $schoolId): bool;

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

    /**
     * @param  array{
     *   session_date?: string,
     *   start_time?: string,
     *   end_time?: string,
     *   room_id?: int|null,
     *   max_grade?: int,
     *   pass_grade?: int,
     *   status?: int
     * }  $fields
     */
    public function updateSessionAllowlisted(int $examSessionId, int $schoolId, array $fields): void;

    public function academicYearExists(int $academicYearId): bool;

    public function examTypeExists(int $examTypeId): bool;

    public function termBelongsToAcademicYear(int $termId, int $academicYearId): bool;

    public function subjectExists(int $subjectId): bool;

    public function roomBelongsToSchool(int $roomId, int $schoolId): bool;

    public function hasCurrentGradeForExam(int $examId, int $schoolId): bool;

    public function hasCurrentGradeForSession(int $examSessionId, int $schoolId): bool;

    public function hasCurrentGradeForExamEnrollment(int $examEnrollmentId, int $schoolId): bool;

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

    /**
     * Withdraw active seats (Registered/Confirmed/Present) for one session.
     *
     * @return list<array{id: int, exam_session_id: int, previous_status: int}>
     */
    public function withdrawActiveEnrollmentsForSession(int $examSessionId, int $schoolId): array;
}
