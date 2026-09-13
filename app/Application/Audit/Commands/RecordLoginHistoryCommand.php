<?php

namespace App\Application\Audit\Commands;

use App\Application\Contracts\Command;

final readonly class RecordLoginHistoryCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public int $loginStatus,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $idempotencyKey,
    ) {}
}
