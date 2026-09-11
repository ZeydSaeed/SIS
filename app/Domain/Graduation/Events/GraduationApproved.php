<?php

namespace App\Domain\Graduation\Events;

use App\Domain\Shared\DomainEvent;

final readonly class GraduationApproved implements DomainEvent
{
    public function __construct(
        private int $approvalId,
        private int $schoolId,
        private int $enrollmentId,
        private int $completionOutcomeVersionId,
        private int $decidedBy,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'approval_id' => $this->approvalId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'completion_outcome_version_id' => $this->completionOutcomeVersionId,
            'decided_by' => $this->decidedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
