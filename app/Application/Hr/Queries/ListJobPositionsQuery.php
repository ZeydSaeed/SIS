<?php

namespace App\Application\Hr\Queries;

final readonly class ListJobPositionsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $status = null,
    ) {}
}
