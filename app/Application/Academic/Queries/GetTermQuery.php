<?php

namespace App\Application\Academic\Queries;

final readonly class GetTermQuery
{
    public function __construct(
        public int $termId,
    ) {}
}
