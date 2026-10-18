<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/** Takes a leave type out of use (no new requests; existing ones keep it) or back into use. */
final readonly class ChangeLeaveTypeStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $typeId,
        public bool $active,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
