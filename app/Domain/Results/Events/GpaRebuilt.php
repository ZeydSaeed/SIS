<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class GpaRebuilt implements DomainEvent
{
    public function __construct(
        private int $gpaResultId,
        private int $schoolId,
        private int $enrollmentId,
        private int $academicYearId,
        private int $resultVersion,
        private string $mode,
        private bool $unchanged,
        private ?string $gpaValue,
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
            'academic_year_id' => $this->academicYearId,
            'result_version' => $this->resultVersion,
            'mode' => $this->mode,
            'unchanged' => $this->unchanged,
            'gpa_value' => $this->gpaValue,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
