<?php

namespace App\Domain\Exams\Repositories;

use App\Domain\Exams\Data\CreateStudentGradeData;
use App\Domain\Exams\Data\ExamEnrollmentGradeContext;
use App\Domain\Exams\Data\StudentGradeSnapshot;

interface StudentGradeRepositoryInterface
{
    public function findExamEnrollmentContext(int $examEnrollmentId, int $schoolId): ?ExamEnrollmentGradeContext;

    public function findByIdentity(int $gradeId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot;

    public function findCurrentForExamEnrollment(int $examEnrollmentId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot;

    public function lockCurrentForExamEnrollment(int $examEnrollmentId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot;

    public function lockByIdentity(int $gradeId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot;

    public function insert(CreateStudentGradeData $data): int;

    public function markVoided(int $gradeId, int $academicYearId): void;

    public function markFinalized(int $gradeId, int $academicYearId, string $finalizedAt): void;

    /**
     * @return list<int>
     */
    public function correctionChainIds(int $gradeId, int $academicYearId, int $maxDepth = 32): array;
}
