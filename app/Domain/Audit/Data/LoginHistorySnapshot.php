<?php

namespace App\Domain\Audit\Data;

final readonly class LoginHistorySnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public int $loginStatus,
        public string $createdAt,
    ) {}
}
