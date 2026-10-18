<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/**
 * Cancels an approved request with a reason (e.g. the leave was not taken). The request and its history stay;
 * any deducted balance is restored by one ledger entry.
 */
final readonly class CancelLeaveRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public int $expectedVersion,
        public string $note,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
