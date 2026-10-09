<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Domain\Organization\Data\RoomDetails;

/** «الغرف الدراسية»: creates a room (roomId null — branch and code required) or edits one. */
final readonly class SaveRoomCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $roomId,
        public ?int $branchId,
        public ?string $code,
        public RoomDetails $details,
        public ?string $idempotencyKey,
    ) {}
}
