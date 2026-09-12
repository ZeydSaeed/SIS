<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AnnualResultCalculated implements DomainEvent
{
    public function __construct(
        private int $annualResultId,
        private int $schoolId,
        private int $enrollmentId,
        private int $studentId,
        private int $academicYearId,
        private int $resultVersion,
        private ?string $averageWeightedTotal,
        private bool $incomplete,
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
            'annual_result_id' => $this->annualResultId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'result_version' => $this->resultVersion,
            'average_weighted_total' => $this->averageWeightedTotal,
            'incomplete' => $this->incomplete,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
