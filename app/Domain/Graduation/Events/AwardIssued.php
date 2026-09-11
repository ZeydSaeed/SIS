<?php

namespace App\Domain\Graduation\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AwardIssued implements DomainEvent
{
    public function __construct(
        private int $awardId,
        private int $awardVersionId,
        private int $schoolId,
        private int $enrollmentId,
        private int $approvalId,
        private ?int $issuedBy,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'award_id' => $this->awardId,
            'award_version_id' => $this->awardVersionId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'approval_id' => $this->approvalId,
            'issued_by' => $this->issuedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
