<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * On request only (never automatic): adds the common leave types the school does not have yet, as a starting
 * point. No entitlement or legal limit is assumed — the school sets them in each type's policy.
 */
final readonly class AddSuggestedLeaveTypesCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
