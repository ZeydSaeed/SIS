<?php

namespace App\Application\Vocational\Queries;

final readonly class GetWorkshopQuery
{
    public function __construct(
        public int $schoolId,
        public int $workshopId,
    ) {}
}
