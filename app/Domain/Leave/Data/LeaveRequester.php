<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\ValueObjects\RequesterKind;

/** The staff member a request is for, as the school knows them that year (membership checked by the directory). */
final readonly class LeaveRequester
{
    public function __construct(
        public RequesterKind $kind,
        public int $personId,
        public ?int $userId,
        public string $name,
    ) {}

    public function teacherId(): ?int
    {
        return $this->kind === RequesterKind::Teacher ? $this->personId : null;
    }

    public function employeeId(): ?int
    {
        return $this->kind === RequesterKind::Employee ? $this->personId : null;
    }

    public function isUser(int $userId): bool
    {
        return $this->userId !== null && $this->userId === $userId;
    }
}
