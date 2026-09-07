<?php

namespace App\Domain\Enrollment\Events;

use App\Domain\Shared\DomainEvent;

final readonly class EnrollmentCancelled implements DomainEvent
{
    public function __construct(
        private int $enrollmentId,
        private int $studentId,
        private int $schoolId,
        private int $academicYearId,
        private string $effectiveTo,
        private ?int $cancelledBy,
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
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'effective_to' => $this->effectiveTo,
            'cancelled_by' => $this->cancelledBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
