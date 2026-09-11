<?php

namespace App\Domain\Graduation\Events;

use App\Domain\Shared\DomainEvent;

final readonly class AwardRevoked implements DomainEvent
{
    public function __construct(
        private int $revocationId,
        private int $awardVersionId,
        private int $schoolId,
        private string $reasonRef,
        private ?int $revokedBy,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'revocation_id' => $this->revocationId,
            'award_version_id' => $this->awardVersionId,
            'school_id' => $this->schoolId,
            'reason_ref' => $this->reasonRef,
            'revoked_by' => $this->revokedBy,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
