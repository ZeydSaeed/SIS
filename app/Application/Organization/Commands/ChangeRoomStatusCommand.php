<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

/** «الغرف الدراسية»: takes a room out of service (refused while lessons, activities or workshops use it) or back. */
final readonly class ChangeRoomStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $roomId,
        public bool $active,
        public ?string $idempotencyKey,
    ) {}
}
