<?php

namespace App\Domain\Graduation\Events;

use App\Domain\Shared\DomainEvent;

final readonly class CompletionEvaluated implements DomainEvent
{
    public function __construct(
        private int $completionOutcomeId,
        private int $completionOutcomeVersionId,
        private int $schoolId,
        private int $enrollmentId,
        private int $eligibilityStatus,
        private ?int $evaluatedBy,
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
            'completion_outcome_version_id' => $this->completionOutcomeVersionId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'eligibility_status' => $this->eligibilityStatus,
            'evaluated_by' => $this->evaluatedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
