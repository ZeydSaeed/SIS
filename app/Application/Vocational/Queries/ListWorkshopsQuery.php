<?php

namespace App\Application\Vocational\Queries;

final readonly class ListWorkshopsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $status = null,
    ) {}
}
