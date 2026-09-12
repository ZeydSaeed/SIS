<?php

namespace App\Domain\Promotion\Events;

use App\Domain\Shared\DomainEvent;

final readonly class PromotionDecisionRecorded implements DomainEvent
{
    public function __construct(
        private int $recordId,
        private int $schoolId,
        private int $enrollmentId,
        private int $academicYearId,
        private int $promotionStatus,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'record_id' => $this->recordId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'academic_year_id' => $this->academicYearId,
            'promotion_status' => $this->promotionStatus,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
