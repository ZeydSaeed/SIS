<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/** The requester (or a holder of «إدارة طلبات الإجازة») takes back a request that is not decided yet. */
final readonly class WithdrawLeaveRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public int $expectedVersion,
        public ?string $note,
        public int $actorUserId,
        public bool $canManage,
        public ?string $idempotencyKey,
    ) {}
}
