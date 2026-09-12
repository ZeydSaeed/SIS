<?php

namespace App\Domain\Transfers\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TransferCompleted implements DomainEvent
{
    public function __construct(
        private int $transferRequestId,
        private int $transferRecordId,
        private int $fromSchoolId,
        private int $toSchoolId,
        private int $fromEnrollmentId,
        private int $toEnrollmentId,
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
            'transfer_request_id' => $this->transferRequestId,
            'transfer_record_id' => $this->transferRecordId,
            'from_school_id' => $this->fromSchoolId,
            'to_school_id' => $this->toSchoolId,
            'from_enrollment_id' => $this->fromEnrollmentId,
            'to_enrollment_id' => $this->toEnrollmentId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
