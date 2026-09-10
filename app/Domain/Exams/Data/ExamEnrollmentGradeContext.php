<?php

namespace App\Domain\Exams\Data;

final readonly class ExamEnrollmentGradeContext
{
    public function __construct(
        public int $examEnrollmentId,
        public int $schoolId,
        public int $examSessionId,
        public int $enrollmentId,
        public int $studentId,
        public int $subjectId,
        public int $academicYearId,
        public int $examId,
        public int $examStatus,
        public int $sessionStatus,
        public int $examEnrollmentStatus,
        public int $academicEnrollmentStatus,
        public ?string $academicEnrollmentEffectiveTo,
        public string $maxScore,
    ) {}
}
