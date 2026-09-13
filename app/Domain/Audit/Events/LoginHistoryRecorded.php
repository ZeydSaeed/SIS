<?php

namespace App\Domain\Audit\Events;

use App\Domain\Shared\DomainEvent;

final readonly class LoginHistoryRecorded implements DomainEvent
{
    public function __construct(
        private int $loginHistoryId,
        private int $schoolId,
        private int $userId,
        private int $loginStatus,
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
            'login_history_id' => $this->loginHistoryId,
            'school_id' => $this->schoolId,
            'user_id' => $this->userId,
            'login_status' => $this->loginStatus,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
