<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/** The approver (the actor) lets another user decide the school's leave from `dateFrom` to `dateTo`. */
final readonly class CreateLeaveDelegationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $actorUserId,
        public int $delegateUserId,
        public string $dateFrom,
        public string $dateTo,
        public string $today,
        public ?string $idempotencyKey,
    ) {}
}
