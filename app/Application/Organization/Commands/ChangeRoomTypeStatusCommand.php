<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

/** «أنواع الغرف»: retires one of the school's own types (refused while active rooms use it) or brings it back. */
final readonly class ChangeRoomTypeStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $typeId,
        public bool $active,
        public ?string $idempotencyKey,
    ) {}
}
