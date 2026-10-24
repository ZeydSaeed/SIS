<?php

namespace App\Application\Results\Queries;

use App\Application\Results\Contracts\PortalPublishedResultsReadRepositoryInterface;

final class GetPortalPublishedResultsHandler
{
    public function __construct(
        private readonly PortalPublishedResultsReadRepositoryInterface $results,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(GetPortalPublishedResultsQuery $query): array
    {
        return $this->results->forStudents($query->schoolId, $query->studentIds);
    }
}
