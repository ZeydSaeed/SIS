<?php

namespace App\Domain\Organization\Events;

use App\Domain\Shared\DomainEvent;

/** «الفروع والاختصاصات»: a department was created, updated or deleted (deactivated). */
final readonly class DepartmentChanged implements DomainEvent
{
    public function __construct(
        private int $departmentId,
        private int $schoolId,
        private string $change,
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
            'department_id' => $this->departmentId,
            'school_id' => $this->schoolId,
            'change' => $this->change,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
