<?php

namespace App\Domain\Finance\Events;

use App\Domain\Shared\DomainEvent;

final readonly class StudentFeeAssigned implements DomainEvent
{
    public function __construct(
        private int $studentFeeId,
        private int $schoolId,
        private int $enrollmentId,
        private int $feeTypeId,
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
            'student_fee_id' => $this->studentFeeId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'fee_type_id' => $this->feeTypeId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
