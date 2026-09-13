<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class CreateWorkshopCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public int $capacity,
        public int $safetyCapacity,
        public ?int $roomId,
        public ?string $idempotencyKey,
    ) {}
}
