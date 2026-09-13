<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class CreateWorkshopEquipmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $workshopId,
        public string $code,
        public string $name,
        public int $quantity,
        public ?string $idempotencyKey,
    ) {}
}
