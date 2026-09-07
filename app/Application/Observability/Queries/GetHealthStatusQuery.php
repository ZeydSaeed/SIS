<?php

namespace App\Application\Observability\Queries;

use App\Application\Contracts\Query;

final readonly class GetHealthStatusQuery implements Query
{
    public function __construct(
        public string $correlationId,
    ) {}
}
