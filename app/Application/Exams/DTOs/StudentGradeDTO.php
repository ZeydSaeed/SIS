<?php

namespace App\Application\Exams\DTOs;

final readonly class StudentGradeDTO
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
        public ?string $enteredAt,
        public ?string $finalizedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academicYearId,
            'school_id' => $this->schoolId,
            'exam_enrollment_id' => $this->examEnrollmentId,
            'exam_session_id' => $this->examSessionId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'subject_id' => $this->subjectId,
            'score' => $this->score,
            'max_score' => $this->maxScore,
            'is_absent' => $this->isAbsent,
            'status' => $this->status,
            'is_current' => $this->isCurrent,
            'correction_of_grade_id' => $this->correctionOfGradeId,
            'entered_at' => $this->enteredAt,
            'finalized_at' => $this->finalizedAt,
        ];
    }
}
