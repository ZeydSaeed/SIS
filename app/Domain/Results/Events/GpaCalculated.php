<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class GpaCalculated implements DomainEvent
{
    public function __construct(
        private int $gpaResultId,
        private int $schoolId,
        private int $enrollmentId,
        private int $studentId,
        private int $academicYearId,
        private int $resultVersion,
        private ?string $gpaValue,
        private string $scaleCode,
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
            'gpa_result_id' => $this->gpaResultId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'result_version' => $this->resultVersion,
            'gpa_value' => $this->gpaValue,
            'scale_code' => $this->scaleCode,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
