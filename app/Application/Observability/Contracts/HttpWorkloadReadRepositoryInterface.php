<?php

namespace App\Application\Observability\Contracts;

interface HttpWorkloadReadRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function latestSummary(): ?array;
}
