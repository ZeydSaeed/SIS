<?php

namespace App\Domain\Organization\Events;

use App\Domain\Shared\DomainEvent;

/** «الفروع والاختصاصات»: a branch was created, updated or deleted (deactivated). */
final readonly class BranchChanged implements DomainEvent
{
    public function __construct(
        private int $branchId,
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
            'branch_id' => $this->branchId,
            'school_id' => $this->schoolId,
            'change' => $this->change,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
