<?php

namespace App\Application\Organization\Queries;

final readonly class ListRoomsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $branchId = null,
    ) {}
}
