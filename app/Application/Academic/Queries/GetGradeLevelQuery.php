<?php

namespace App\Application\Academic\Queries;

final readonly class GetGradeLevelQuery
{
    public function __construct(
        public int $gradeLevelId,
    ) {}
}
