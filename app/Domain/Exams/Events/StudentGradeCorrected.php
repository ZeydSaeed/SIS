<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentGradeCorrected implements DomainEvent
{
    public function __construct(
        private int $previousGradeId,
        private int $newGradeId,
        private int $academicYearId,
        private int $schoolId,
        private int $examEnrollmentId,
        private int $studentId,
        private ?string $score,
        private string $maxScore,
        private bool $isAbsent,
        private ?int $correctedBy,
        private string $reason,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'previous_grade_id' => $this->previousGradeId,
            'new_grade_id' => $this->newGradeId,
            'academic_year_id' => $this->academicYearId,
            'school_id' => $this->schoolId,
            'exam_enrollment_id' => $this->examEnrollmentId,
            'student_id' => $this->studentId,
            'score' => $this->score,
            'max_score' => $this->maxScore,
            'is_absent' => $this->isAbsent,
            'corrected_by' => $this->correctedBy,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
