<?php

namespace App\Application\Observability\Queries;

use App\Application\Contracts\Query;

final readonly class GetPrometheusMetricsQuery implements Query
{
    public function __construct(
        public ?string $correlationId = null,
    ) {}
}
