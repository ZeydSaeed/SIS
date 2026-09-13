<?php

namespace App\Domain\Hr\Events;

use App\Domain\Shared\DomainEvent;

final readonly class EmployeeReactivated implements DomainEvent
{
    public function __construct(
        private int $employeeId,
        private int $schoolId,
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
            'employee_id' => $this->employeeId,
            'school_id' => $this->schoolId,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
