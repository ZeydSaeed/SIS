<?php

namespace Tests\Support\Exams;

use App\Domain\Exams\Data\CreateExamData;
use App\Domain\Exams\Data\CreateExamEnrollmentData;
use App\Domain\Exams\Data\CreateExamSessionData;
use App\Domain\Exams\Data\ExamEnrollmentSnapshot;
use App\Domain\Exams\Data\ExamSessionSnapshot;
use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

/**
 * Test double: delegates all ExamRepositoryInterface calls except grade-enrollment lookup.
 */
final class ExamRepositoryGradeLookupFailingDecorator implements ExamRepositoryInterface
{
    public function __construct(private readonly ExamRepositoryInterface $inner) {}

    public function insert(CreateExamData $data): int
    {
        return $this->inner->insert($data);
    }

    public function insertSession(CreateExamSessionData $data): int
    {
        return $this->inner->insertSession($data);
    }

    public function insertExamEnrollment(CreateExamEnrollmentData $data): int
    {
        return $this->inner->insertExamEnrollment($data);
    }

    public function findExamEnrollmentByIdAndSchool(int $examEnrollmentId, int $schoolId): ?ExamEnrollmentSnapshot
    {
        return $this->inner->findExamEnrollmentByIdAndSchool($examEnrollmentId, $schoolId);
    }

    public function lockExamEnrollmentByIdAndSchool(int $examEnrollmentId, int $schoolId): ?ExamEnrollmentSnapshot
    {
        return $this->inner->lockExamEnrollmentByIdAndSchool($examEnrollmentId, $schoolId);
    }

    public function updateExamEnrollmentAllowlisted(int $examEnrollmentId, int $schoolId, array $fields): void
    {
        $this->inner->updateExamEnrollmentAllowlisted($examEnrollmentId, $schoolId, $fields);
    }

    public function findByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot
    {
        return $this->inner->findByIdAndSchool($examId, $schoolId);
    }

    public function lockByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot
    {
        return $this->inner->lockByIdAndSchool($examId, $schoolId);
    }

    public function findSessionByIdAndSchool(int $examSessionId, int $schoolId): ?ExamSessionSnapshot
    {
        return $this->inner->findSessionByIdAndSchool($examSessionId, $schoolId);
    }

    public function lockSessionByIdAndSchool(int $examSessionId, int $schoolId): ?ExamSessionSnapshot
    {
        return $this->inner->lockSessionByIdAndSchool($examSessionId, $schoolId);
    }

    public function examEnrollmentSeatExists(int $examSessionId, int $enrollmentId, int $schoolId): bool
    {
        return $this->inner->examEnrollmentSeatExists($examSessionId, $enrollmentId, $schoolId);
    }

    public function updateAllowlisted(int $examId, int $schoolId, array $fields): void
    {
        $this->inner->updateAllowlisted($examId, $schoolId, $fields);
    }

    public function updateSessionAllowlisted(int $examSessionId, int $schoolId, array $fields): void
    {
        $this->inner->updateSessionAllowlisted($examSessionId, $schoolId, $fields);
    }

    public function academicYearExists(int $academicYearId): bool
    {
        return $this->inner->academicYearExists($academicYearId);
    }

    public function examTypeExists(int $examTypeId): bool
    {
        return $this->inner->examTypeExists($examTypeId);
    }

    public function termBelongsToAcademicYear(int $termId, int $academicYearId): bool
    {
        return $this->inner->termBelongsToAcademicYear($termId, $academicYearId);
    }

    public function subjectExists(int $subjectId): bool
    {
        return $this->inner->subjectExists($subjectId);
    }

    public function roomBelongsToSchool(int $roomId, int $schoolId): bool
    {
        return $this->inner->roomBelongsToSchool($roomId, $schoolId);
    }

    public function hasCurrentGradeForExam(int $examId, int $schoolId): bool
    {
        return $this->inner->hasCurrentGradeForExam($examId, $schoolId);
    }

    public function hasCurrentGradeForSession(int $examSessionId, int $schoolId): bool
    {
        return $this->inner->hasCurrentGradeForSession($examSessionId, $schoolId);
    }

    public function hasCurrentGradeForExamEnrollment(int $examEnrollmentId, int $schoolId): bool
    {
        throw new \RuntimeException('simulated grade lookup failure');
    }

    public function hasCompletedSessionForExam(int $examId, int $schoolId): bool
    {
        return $this->inner->hasCompletedSessionForExam($examId, $schoolId);
    }

    public function sessionStatusCounts(int $examId, int $schoolId): array
    {
        return $this->inner->sessionStatusCounts($examId, $schoolId);
    }

    public function cancelOpenSessionsForExam(int $examId, int $schoolId): array
    {
        return $this->inner->cancelOpenSessionsForExam($examId, $schoolId);
    }

    public function withdrawActiveEnrollmentsForExam(int $examId, int $schoolId): array
    {
        return $this->inner->withdrawActiveEnrollmentsForExam($examId, $schoolId);
    }

    public function withdrawActiveEnrollmentsForSession(int $examSessionId, int $schoolId): array
    {
        return $this->inner->withdrawActiveEnrollmentsForSession($examSessionId, $schoolId);
    }
}
