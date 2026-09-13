<?php

namespace App\Application\Vocational\Queries;

final readonly class GetWorkshopEquipmentQuery
{
    public function __construct(
        public int $schoolId,
        public int $equipmentId,
    ) {}
}
