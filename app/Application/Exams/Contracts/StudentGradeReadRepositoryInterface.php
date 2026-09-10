<?php

namespace App\Application\Exams\Contracts;

use App\Application\Exams\DTOs\StudentGradeDTO;

interface StudentGradeReadRepositoryInterface
{
    public function findByIdentity(int $gradeId, int $academicYearId, int $schoolId): ?StudentGradeDTO;

    public function findCurrentForExamEnrollment(int $examEnrollmentId, int $academicYearId, int $schoolId): ?StudentGradeDTO;

    /**
     * @return list<StudentGradeDTO>
     */
    public function listForExamSession(int $examSessionId, int $academicYearId, int $schoolId): array;

    /**
     * @return list<StudentGradeDTO>
     */
    public function listForEnrollment(int $enrollmentId, int $academicYearId, int $schoolId): array;
}
