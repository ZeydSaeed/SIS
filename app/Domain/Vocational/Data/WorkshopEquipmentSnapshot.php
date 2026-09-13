<?php

namespace App\Domain\Vocational\Data;

final readonly class WorkshopEquipmentSnapshot
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
