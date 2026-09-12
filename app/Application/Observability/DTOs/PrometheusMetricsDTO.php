<?php

namespace App\Application\Observability\DTOs;

final readonly class PrometheusMetricsDTO
{
    public function __construct(
        public string $body,
        public string $contentType = 'text/plain; version=0.0.4; charset=utf-8',
    ) {}
}
