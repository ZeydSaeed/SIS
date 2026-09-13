<?php

namespace App\Application\Audit\DTOs;

final readonly class LoginHistoryDTO
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
