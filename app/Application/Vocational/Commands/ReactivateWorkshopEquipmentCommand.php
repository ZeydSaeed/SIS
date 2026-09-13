<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateWorkshopEquipmentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $equipmentId,
        public ?string $idempotencyKey,
    ) {}
}
