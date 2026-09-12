<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AnnualResultRebuilt implements DomainEvent
{
    public function __construct(
        private int $annualResultId,
        private int $schoolId,
        private int $enrollmentId,
        private int $academicYearId,
        private int $resultVersion,
        private string $mode,
        private bool $unchanged,
        private ?string $averageWeightedTotal,
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
            'academic_year_id' => $this->academicYearId,
            'result_version' => $this->resultVersion,
            'mode' => $this->mode,
            'unchanged' => $this->unchanged,
            'average_weighted_total' => $this->averageWeightedTotal,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
