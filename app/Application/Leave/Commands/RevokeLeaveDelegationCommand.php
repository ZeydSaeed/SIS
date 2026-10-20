<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/** Ends a delegation now; the delegator, or a leave manager (`$asManager`), may do it. */
final readonly class RevokeLeaveDelegationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $delegationId,
        public int $actorUserId,
        public bool $asManager,
        public ?string $idempotencyKey,
    ) {}
}
