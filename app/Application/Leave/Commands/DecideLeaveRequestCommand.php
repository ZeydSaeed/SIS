<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Domain\Leave\ValueObjects\LeaveDecision;

/**
 * One approval decision on a pending request: approve the next level (the last level approves the request and
 * deducts the balance once), reject with a reason, or return it for more information.
 */
final readonly class DecideLeaveRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public int $expectedVersion,
        public LeaveDecision $decision,
        public ?string $note,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
