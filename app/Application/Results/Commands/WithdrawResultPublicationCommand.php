<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

/** Withdraw an active publication; the reason is kept. Results are re-published as a new publication. */
final readonly class WithdrawResultPublicationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $publicationId,
        public string $reason,
        public ?int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
