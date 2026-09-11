<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;

final readonly class IssueAwardCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $approvalId,
        public int $actorUserId,
        public string $idempotencyKey,
        public string $correlationId,
    ) {}
}
