<?php

namespace App\Domain\Admission\Events;

use App\Domain\Shared\DomainEvent;

final readonly class ApplicationPeriodStatusChanged implements DomainEvent
{
    public function __construct(
        private int $periodId,
        private int $schoolId,
        private int $academicYearId,
        private int $fromStatus,
        private int $toStatus,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'period_id' => $this->periodId,
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->academicYearId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
