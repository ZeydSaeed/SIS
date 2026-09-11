<?php

namespace App\Domain\Graduation\Events;

use App\Domain\Shared\DomainEvent;

final readonly class CompletionOutcomeCreated implements DomainEvent
{
    public function __construct(
        private int $completionOutcomeId,
        private int $schoolId,
        private int $enrollmentId,
        private int $studentId,
        private int $academicYearId,
        private ?int $createdBy,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'completion_outcome_id' => $this->completionOutcomeId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'created_by' => $this->createdBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
