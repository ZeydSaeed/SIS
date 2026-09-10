<?php

namespace App\Domain\Exams\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentGradeVoided implements DomainEvent
{
    public function __construct(
        private int $gradeId,
        private int $academicYearId,
        private int $schoolId,
        private int $examEnrollmentId,
        private int $studentId,
        private ?int $voidedBy,
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
            'grade_id' => $this->gradeId,
            'academic_year_id' => $this->academicYearId,
            'school_id' => $this->schoolId,
            'exam_enrollment_id' => $this->examEnrollmentId,
            'student_id' => $this->studentId,
            'voided_by' => $this->voidedBy,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
