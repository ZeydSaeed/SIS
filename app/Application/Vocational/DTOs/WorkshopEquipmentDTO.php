<?php

namespace App\Application\Vocational\DTOs;

final readonly class WorkshopEquipmentDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $workshopId,
        public string $code,
        public string $name,
        public int $quantity,
        public int $status,
    ) {}
}
