<?php

namespace App\Domain\Exams\Data;

final readonly class StudentGradeSnapshot
{
    public function __construct(
        public int $id,
        public int $academicYearId,
        public int $schoolId,
        public int $examEnrollmentId,
        public int $examSessionId,
        public int $enrollmentId,
        public int $studentId,
        public int $subjectId,
        public ?string $score,
        public string $maxScore,
        public bool $isAbsent,
        public int $status,
        public bool $isCurrent,
        public ?int $correctionOfGradeId,
        public ?int $enteredBy,
        public string $enteredAt,
        public ?string $finalizedAt,
    ) {}
}
