<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;

final readonly class RevokeAwardCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $awardVersionId,
        public string $reasonRef,
        public int $actorUserId,
        public string $idempotencyKey,
        public string $correlationId,
    ) {}
}
